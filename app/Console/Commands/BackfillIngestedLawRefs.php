<?php

namespace App\Console\Commands;

use App\Models\IngestedLaw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillIngestedLawRefs extends Command
{
    protected $signature = 'laws:backfill-ingested-refs {--dry : Preview changes without updating}';
    protected $description = 'Backfill ingested_law_id on laws and law_uploads by matching IngestedLaw doc_id.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $lawsTable = config('vizra-adk.tables.laws', 'laws');
        $uploadsTable = config('vizra-adk.tables.law_uploads', 'law_uploads');
        $ingestedTable = config('vizra-adk.tables.ingested_laws', 'ingested_laws');

        $totalLawRows = 0; $totalUploadRows = 0; $idx = 0;

        IngestedLaw::query()->orderBy('doc_id')->chunk(200, function ($chunk) use ($dry, $lawsTable, $uploadsTable, &$totalLawRows, &$totalUploadRows, &$idx) {
            foreach ($chunk as $ing) {
                $idx++;
                $docId = (string) $ing->doc_id;
                $id = (string) $ing->id;

                // Update laws: exact doc_id match
                $lawQuery = DB::table($lawsTable)
                    ->whereNull('ingested_law_id')
                    ->where('doc_id', $docId);
                $affectedLaws = $dry ? $lawQuery->count() : $lawQuery->update(['ingested_law_id' => $id]);

                // Update uploads: exact doc_id or per-article suffix
                $upQuery = DB::table($uploadsTable)
                    ->whereNull('ingested_law_id')
                    ->where(function ($q) use ($docId) {
                        $q->where('doc_id', $docId)
                          ->orWhere('doc_id', 'like', $docId.'-clanak-%');
                    });
                $affectedUploads = $dry ? $upQuery->count() : $upQuery->update(['ingested_law_id' => $id]);

                $totalLawRows += (int) $affectedLaws;
                $totalUploadRows += (int) $affectedUploads;

                $this->line(sprintf('#%d %s -> laws: %d, uploads: %d', $idx, $docId, (int)$affectedLaws, (int)$affectedUploads));
            }
        });

        $this->info('Done.');
        $this->info('Updated laws rows: '.$totalLawRows);
        $this->info('Updated law_uploads rows: '.$totalUploadRows);
        $this->info('Dry-run: '.($dry ? 'yes' : 'no'));
        return self::SUCCESS;
    }
}

