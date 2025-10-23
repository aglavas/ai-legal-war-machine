<?php

namespace App\Console\Commands;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use App\Models\LegalCase;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateCasesToCourtDecisions extends Command
{
    protected $signature = 'decisions:migrate-from-cases
                            {--all : Migrate all uploads/documents (not only Textract)}
                            {--case-id=* : Limit to specific case ULIDs (can be repeated)}
                            {--chunk=100 : Chunk size for processing cases}
                            {--dry-run : Do not write, only report what would be done}';

    protected $description = 'Migrate cases, case uploads, and case documents to court decision equivalents. By default, only Textract-based uploads/documents are migrated.';

    public function handle(): int
    {
        $onlyTextract = ! $this->option('all');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');
        $limitCaseIds = (array) $this->option('case-id');

        $this->info("Starting migration from cases to court decisions" . ($onlyTextract ? ' (Textract-only)' : ' (ALL uploads)'));

        $query = LegalCase::query();
        if (!empty($limitCaseIds)) {
            $query->whereIn('id', $limitCaseIds);
        }

        $totalCases = (clone $query)->count();
        $this->line("Total cases to consider: {$totalCases}");

        $progress = $this->output->createProgressBar($totalCases);
        $progress->start();

        $stats = [
            'cases_processed' => 0,
            'decisions_created' => 0,
            'decisions_reused' => 0,
            'uploads_created' => 0,
            'uploads_reused' => 0,
            'documents_inserted' => 0,
            'documents_updated' => 0,
            'documents_skipped' => 0,
        ];

        $query->orderBy('id')->chunk($chunkSize, function ($cases) use ($onlyTextract, $dryRun, &$stats, $progress) {
            foreach ($cases as $case) {
                DB::beginTransaction();
                try {
                    // 1) Find or create CourtDecision for this case
                    [$decision, $createdDecision] = $this->findOrCreateDecisionFromCase($case, $dryRun);
                    $stats[$createdDecision ? 'decisions_created' : 'decisions_reused']++;

                    // 2) Build upload mapping (case upload id -> decision upload id)
                    $uploadMap = $this->migrateUploads($case, $decision, $onlyTextract, $dryRun, $stats);

                    // 3) Migrate documents referencing selected uploads
                    $this->migrateDocuments($case, $decision, $uploadMap, $dryRun, $stats, $onlyTextract);

                    if ($dryRun) {
                        DB::rollBack();
                    } else {
                        DB::commit();
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("\nError while migrating case {$case->id}: " . $e->getMessage());
                    throw $e;
                }

                $stats['cases_processed']++;
                $progress->advance();
            }
        });

        $progress->finish();
        $this->newLine(2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Cases processed', $stats['cases_processed']],
                ['Court decisions created', $stats['decisions_created']],
                ['Court decisions reused', $stats['decisions_reused']],
                ['Uploads created', $stats['uploads_created']],
                ['Uploads reused', $stats['uploads_reused']],
                ['Documents inserted', $stats['documents_inserted']],
                ['Documents updated', $stats['documents_updated']],
                ['Documents skipped', $stats['documents_skipped']],
            ]
        );

        $this->info('Migration complete.');
        return self::SUCCESS;
    }

    protected function findOrCreateDecisionFromCase(LegalCase $case, bool $dryRun): array
    {
        // Use case_number + court as a stable identity; fall back to id
        $builder = CourtDecision::query();
        if (!empty($case->case_number)) {
            $builder->where('case_number', $case->case_number);
        } else {
            $builder->where('case_number', $case->id);
        }
        if (!empty($case->court)) {
            $builder->where('court', $case->court);
        }

        $decision = $builder->first();
        if ($decision) {
            // Optionally update metadata (title, tags, description) if empty
            $updates = [];
            foreach ([
                'title' => $case->title,
                'jurisdiction' => $case->jurisdiction,
                'judge' => $case->judge,
                'tags' => $case->tags,
                'description' => $case->description,
            ] as $k => $v) {
                if ((empty($decision->{$k}) || $decision->{$k} === null) && !empty($v)) {
                    $updates[$k] = $v;
                }
            }
            // Filing date maps loosely to decision_date if missing
            if (empty($decision->decision_date) && !empty($case->filing_date)) {
                $updates['decision_date'] = $case->filing_date;
            }
            if (!empty($updates)) {
                $updates['updated_at'] = now();
                if (!$dryRun) {
                    $decision->fill($updates)->save();
                }
            }
            return [$decision, false];
        }

        // Create new CourtDecision
        $decision = new CourtDecision();
        $decision->id = (string) Str::ulid();
        $decision->case_number = $case->case_number ?: $case->id;
        $decision->title = $case->title;
        $decision->court = $case->court;
        $decision->jurisdiction = $case->jurisdiction;
        $decision->judge = $case->judge;
        $decision->decision_date = $case->filing_date; // best-effort mapping
        $decision->tags = $case->tags;
        $decision->description = $case->description;
        $decision->created_at = now();
        $decision->updated_at = now();

        if (!$dryRun) {
            $decision->save();
        }
        return [$decision, true];
    }

    /**
     * Migrate CaseDocumentUpload entries to CourtDecisionDocumentUpload
     * Returns a map of [old_upload_id => new_upload_id]
     */
    protected function migrateUploads(LegalCase $case, CourtDecision $decision, bool $onlyTextract, bool $dryRun, array &$stats): array
    {
        $uploadsQuery = CaseDocumentUpload::query()->where('case_id', $case->id);
        if ($onlyTextract) {
            $uploadsQuery->where(function ($q) {
                $q->where('local_path', 'like', '%textract/%')
                  ->orWhere('source_url', 'like', '%textract%');
            });
        }

        $uploads = $uploadsQuery->get();
        $map = [];
        foreach ($uploads as $upload) {
            // Find existing by strong identity: decision_id + sha256 (if present), else doc_id + local_path
            $existing = null;
            if (!empty($upload->sha256)) {
                $existing = CourtDecisionDocumentUpload::query()
                    ->where('decision_id', $decision->id)
                    ->where('sha256', $upload->sha256)
                    ->first();
            }
            if (!$existing) {
                $existing = CourtDecisionDocumentUpload::query()
                    ->where('decision_id', $decision->id)
                    ->where('doc_id', $upload->doc_id)
                    ->where('local_path', $upload->local_path)
                    ->first();
            }

            if ($existing) {
                $map[$upload->id] = $existing->id;
                $stats['uploads_reused']++;
                continue;
            }

            $new = new CourtDecisionDocumentUpload();
            $new->id = (string) Str::ulid();
            $new->decision_id = $decision->id;
            $new->doc_id = $upload->doc_id;
            $new->disk = $upload->disk;
            $new->local_path = $upload->local_path;
            $new->original_filename = $upload->original_filename;
            $new->mime_type = $upload->mime_type;
            $new->file_size = $upload->file_size;
            $new->sha256 = $upload->sha256;
            $new->source_url = $upload->source_url;
            $new->uploaded_at = $upload->uploaded_at ? Carbon::parse($upload->uploaded_at) : null;
            $new->status = $upload->status;
            $new->error = $upload->error;
            $new->created_at = now();
            $new->updated_at = now();

            if (!$dryRun) {
                $new->save();
            }

            $map[$upload->id] = $new->id;
            $stats['uploads_created']++;
        }

        return $map;
    }

    /**
     * Migrate CaseDocument entries to CourtDecisionDocument using Eloquent so casts handle JSON properly
     */
    protected function migrateDocuments(LegalCase $case, CourtDecision $decision, array $uploadMap, bool $dryRun, array &$stats, bool $onlyTextract): void
    {
        $docQuery = CaseDocument::query()->where('case_id', $case->id);
        if ($onlyTextract) {
            $allowedUploadIds = array_keys($uploadMap);
            if (empty($allowedUploadIds)) {
                return;
            }
            $docQuery->whereIn('upload_id', $allowedUploadIds);
        }

        $docs = $docQuery->get();
        $driver = DB::connection()->getDriverName();
        $table = (new CourtDecisionDocument())->getTable();

        foreach ($docs as $doc) {
            if ($onlyTextract && $doc->upload_id && !isset($uploadMap[$doc->upload_id])) {
                $stats['documents_skipped']++;
                continue;
            }

            $now = now();
            $uploadId = $doc->upload_id ? ($uploadMap[$doc->upload_id] ?? null) : null;
            $payload = [
                'decision_id' => $decision->id,
                'content_hash' => (string) $doc->content_hash,
                'doc_id' => (string) $doc->doc_id,
                'upload_id' => $uploadId,
                'title' => $doc->title,
                'category' => $doc->category,
                'author' => $doc->author,
                'language' => $doc->language,
                'tags' => is_array($doc->tags) ? json_encode($doc->tags, JSON_UNESCAPED_UNICODE) : $doc->tags,
                'chunk_index' => (int) $doc->chunk_index,
                'content' => (string) $doc->content,
                'metadata' => is_array($doc->metadata) ? json_encode($doc->metadata, JSON_UNESCAPED_UNICODE) : $doc->metadata,
                'source' => $doc->source,
                'source_id' => $doc->source_id,
                'embedding_provider' => (string) $doc->embedding_provider,
                'embedding_model' => (string) $doc->embedding_model,
                'embedding_dimensions' => (int) $doc->embedding_dimensions,
                'embedding_norm' => $doc->embedding_norm !== null ? (float) $doc->embedding_norm : null,
                'token_count' => $doc->token_count !== null ? (int) $doc->token_count : null,
                'updated_at' => $now,
            ];

            // Embedding column
            $attrs = $doc->getAttributes();
            if ($driver === 'pgsql') {
                if (array_key_exists('embedding', $attrs) && $attrs['embedding'] !== null) {
                    $payload['embedding'] = $attrs['embedding'];
                }
            } else {
                $vector = $attrs['embedding_vector'] ?? ($attrs['embedding'] ?? null);
                if ($vector !== null) {
                    $payload['embedding_vector'] = is_string($vector) ? $vector : json_encode($vector);
                }
            }

            // Dry-run estimation
            if ($dryRun) {
                $exists = DB::table($table)
                    ->where('decision_id', $decision->id)
                    ->where('content_hash', $doc->content_hash)
                    ->exists();
                if ($exists) {
                    $stats['documents_updated']++;
                } else {
                    $stats['documents_inserted']++;
                }
                continue;
            }

            // Upsert by manual check to keep payload scalar
            $existing = DB::table($table)
                ->where('decision_id', $decision->id)
                ->where('content_hash', $doc->content_hash)
                ->first();

            if ($existing) {
                DB::table($table)
                    ->where('decision_id', $decision->id)
                    ->where('content_hash', $doc->content_hash)
                    ->update($payload);
                $stats['documents_updated']++;
            } else {
                $payload['id'] = (string) Str::ulid();
                $payload['created_at'] = $now;
                DB::table($table)->insert($payload);
                $stats['documents_inserted']++;
            }
        }
    }
}
