<?php

namespace Tests\Feature;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrateCasesToCourtDecisionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrates_textract_uploads_and_documents_to_court_decisions(): void
    {
        // Arrange: create a LegalCase with a Textract-like upload and a document chunk
        $case = new LegalCase([
            'id' => (string) Str::ulid(),
            'case_number' => 'P-123/2025',
            'title' => 'Client vs Opponent',
            'client_name' => 'Client',
            'opponent_name' => 'Opponent',
            'court' => 'Zg Sud',
            'jurisdiction' => 'Zagreb',
            'judge' => 'John Doe',
            'filing_date' => '2025-10-05',
            'status' => 'open',
            'tags' => ['textract','pdf'],
            'description' => 'A sample case',
        ]);
        $case->save();

        $upload = new CaseDocumentUpload([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'doc_id' => 'doc-1',
            'disk' => 'local',
            'local_path' => 'textract/input/abc123.pdf',
            'original_filename' => 'abc123.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            'source_url' => 's3://bucket/textract/input/abc123.pdf',
            'uploaded_at' => now(),
            'status' => 'stored',
            'error' => null,
        ]);
        $upload->save();

        $doc = new CaseDocument([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'doc_id' => 'doc-1',
            'upload_id' => $upload->id,
            'title' => 'Document Title',
            'category' => 'pleading',
            'author' => 'Author',
            'language' => 'hr',
            'tags' => ['chunked'],
            'chunk_index' => 0,
            'content' => 'Sample content for embedding.',
            'metadata' => json_encode(['page' => 1], JSON_UNESCAPED_UNICODE),
            'source' => 'textract',
            'source_id' => 'abc123',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 5, 0.1),
            'embedding_norm' => 1.0,
            'content_hash' => 'hash123',
            'token_count' => 10,
        ]);
        $doc->save();

        // Act: run the migration command (default: Textract-only)
        $this->artisan('decisions:migrate-from-cases')
            ->assertExitCode(0);

        // Assert: CourtDecision created
        $decision = CourtDecision::where('case_number', 'P-123/2025')->where('court', 'Zg Sud')->first();
        $this->assertNotNull($decision, 'CourtDecision should be created');

        // Assert: Upload migrated
        $migratedUpload = CourtDecisionDocumentUpload::where('decision_id', $decision->id)
            ->where('sha256', $upload->sha256)
            ->first();
        $this->assertNotNull($migratedUpload, 'CourtDecisionDocumentUpload should be created');
        $this->assertEquals('doc-1', $migratedUpload->doc_id);

        // Assert: Document migrated and linked to migrated upload
        $migratedDoc = CourtDecisionDocument::where('decision_id', $decision->id)
            ->where('content_hash', 'hash123')
            ->first();
        $this->assertNotNull($migratedDoc, 'CourtDecisionDocument should be created');
        $this->assertEquals($migratedUpload->id, $migratedDoc->upload_id);
        $this->assertEquals('openai', $migratedDoc->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $migratedDoc->embedding_model);

        // Idempotency: run again, expect no duplicates
        $this->artisan('decisions:migrate-from-cases')
            ->assertExitCode(0);

        $this->assertEquals(1, CourtDecision::count());
        $this->assertEquals(1, CourtDecisionDocumentUpload::count());
        $this->assertEquals(1, CourtDecisionDocument::count());
    }
}

