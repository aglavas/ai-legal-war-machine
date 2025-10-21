# Court Decision Refactoring Summary

## Overview
Refactored the Odluke (court decisions) system to use dedicated `CourtDecision` models and tables instead of incorrectly using `LegalCase` models. This separation ensures proper domain modeling where:
- **CourtDecision** = Historical court practice/decisions from external sources (Odluke)
- **LegalCase** = Client-specific legal cases with their documents

## Changes Made

### 1. New Database Tables
Created three new tables with migrations:

#### `court_decisions` (2025_10_17_000000)
Stores court decision metadata from Odluke:
- `id` (ULID)
- `case_number` - Decision number (broj_odluke)
- `title` - Decision title
- `court` - Court name
- `jurisdiction` - Legal jurisdiction (e.g., 'HR')
- `judge` - Judge name
- `decision_date` - Date of decision (datum_odluke)
- `publication_date` - Date of publication (datum_objave)
- `decision_type` - Type of decision (vrsta_odluke)
- `register` - Register type (upisnik)
- `finality` - Finality status (pravomocnost)
- `ecli` - European Case Law Identifier
- `tags` - JSON array of tags
- `description` - Text description
- Indexes on: `case_number`, `court`, `jurisdiction`, `decision_date`, `publication_date`, `ecli`

#### `court_decision_document_uploads` (2025_10_17_000010)
Stores PDF/HTML uploads for court decisions:
- `id` (ULID)
- `decision_id` - Foreign key to court_decisions
- `doc_id` - Document identifier
- `disk`, `local_path` - Storage information
- `original_filename`, `mime_type`, `file_size` - File metadata
- `sha256` - File hash
- `source_url` - Original source URL
- `uploaded_at`, `status`, `error` - Upload tracking

#### `court_decision_documents` (2025_10_17_000020)
Stores chunked, embedded court decision content:
- `id` (ULID)
- `decision_id` - Foreign key to court_decisions
- `doc_id` - Document identifier
- `upload_id` - Optional foreign key to uploads
- `title`, `category`, `author`, `language`, `tags` - Metadata
- `chunk_index`, `content`, `metadata` - Content chunking
- `source`, `source_id` - Source tracking
- `embedding_provider`, `embedding_model`, `embedding_dimensions` - Embedding info
- `embedding` (vector) or `embedding_vector` (JSON) - Vector embeddings
- `embedding_norm`, `content_hash`, `token_count` - Technical metadata
- Vector index support for pgvector

### 2. New Models

#### `App\Models\CourtDecision`
- Replaces misuse of `LegalCase` for Odluke
- Relationships: `documents()`, `uploads()`
- Table: configurable via `vizra-adk.tables.court_decisions`

#### `App\Models\CourtDecisionDocument`
- Stores embedded document chunks
- Relationships: `decision()`, `upload()`
- Table: configurable via `vizra-adk.tables.court_decision_documents`

#### `App\Models\CourtDecisionDocumentUpload`
- Tracks file uploads
- Relationship: `decision()`
- Table: configurable via `vizra-adk.tables.court_decision_document_uploads`

### 3. New Services

#### `App\Services\CourtDecisionVectorStoreService`
Handles vector embeddings for court decisions:
- `ingest()` - Ingests documents with embeddings
- Supports both PostgreSQL pgvector and JSON storage
- Graph database sync support (if implemented)
- Duplicate detection via content hash

### 4. Updated Services

#### `App\Services\Odluke\OdlukeIngestService`
Completely refactored to use `CourtDecision` models:
- Changed from `LegalCase` to `CourtDecision`
- Changed from `CaseVectorStoreService` to `CourtDecisionVectorStoreService`
- Changed from `CaseDocumentUpload` to `CourtDecisionDocumentUpload`
- Storage path changed from `cases/{court}/{num}` to `court_decisions/{court}/{num}`
- Method `upsertCaseFromMeta()` → `upsertDecisionFromMeta()`
- Now properly handles ECLI identifiers
- Improved metadata mapping for Croatian court fields

### 5. Configuration Updates

#### `config/vizra-adk.php`
Added new table configurations:
```php
'tables' => [
    // ...existing tables...
    
    // Court decisions (Odluke) - historical court practice
    'court_decisions' => 'court_decisions',
    'court_decision_documents' => 'court_decision_documents',
    'court_decision_document_uploads' => 'court_decision_document_uploads',
],
```

### 6. Preserved Structures (Unchanged)

The following remain for client cases:
- `LegalCase` model and `cases` table
- `CaseDocument` model and `cases_documents` table
- `CaseDocumentUpload` model and `cases_documents_uploads` table
- `CaseVectorStoreService`

## Data Flow

### Odluke/Court Decisions (New)
```
OdlukeClient → OdlukeIngestService
    ↓
CourtDecision (metadata)
    ↓
CourtDecisionDocumentUpload (PDF/HTML files)
    ↓
CourtDecisionVectorStoreService
    ↓
CourtDecisionDocument (embedded chunks)
```

### Client Cases (Existing - Unchanged)
```
Google Drive → Textract Pipeline
    ↓
LegalCase (client case metadata)
    ↓
CaseDocumentUpload (client documents)
    ↓
CaseVectorStoreService
    ↓
CaseDocument (embedded chunks)
```

## Migration Guide

### Running Migrations
```bash
php artisan migrate
```

This creates the three new tables:
1. `court_decisions`
2. `court_decision_document_uploads`
3. `court_decision_documents`

### Migrating Existing Data (if needed)
If you have existing Odluke data in the `cases` table, you'll need to:

1. Identify records that are court decisions (likely by checking tags or source)
2. Copy them to the new `court_decisions` table
3. Update related records in `cases_documents` to `court_decision_documents`
4. Update related records in `cases_documents_uploads` to `court_decision_document_uploads`

**Note**: Consider creating a migration script if you have significant existing data.

## Usage Examples

### Ingesting Court Decisions
```php
use App\Services\Odluke\OdlukeIngestService;

$odlukeService = app(OdlukeIngestService::class);

// Ingest by IDs
$result = $odlukeService->ingestByIds(['123456', '789012'], [
    'model' => 'text-embedding-3-small',
    'chunk_chars' => 1500,
    'overlap' => 200,
    'prefer' => 'html', // or 'pdf' or 'auto'
]);

// Ingest from text
$result = $odlukeService->ingestText($decisionText, [
    'broj_odluke' => 'Rev-123/2024',
    'sud' => 'Vrhovni sud Republike Hrvatske',
    'ecli' => 'ECLI:HR:VSRH:2024:123',
], [
    'model' => 'text-embedding-3-small',
]);
```

### Querying Court Decisions
```php
use App\Models\CourtDecision;

// Find by ECLI
$decision = CourtDecision::where('ecli', 'ECLI:HR:VSRH:2024:123')->first();

// Find by case number and court
$decision = CourtDecision::where('case_number', 'Rev-123/2024')
    ->where('court', 'Vrhovni sud Republike Hrvatske')
    ->first();

// Get decision with documents
$decision = CourtDecision::with('documents', 'uploads')->find($id);
```

## Benefits of This Refactoring

1. **Clear Domain Separation**: Court decisions and client cases are now properly separated
2. **Improved Data Integrity**: Foreign keys reference the correct entities
3. **Better Scalability**: Each entity can evolve independently
4. **Clearer Business Logic**: Code intent is more obvious
5. **Specialized Fields**: Court decisions have fields specific to their needs (ECLI, finality, etc.)
6. **Storage Organization**: Files are stored in separate directories

## Next Steps

1. ✅ Create and run migrations
2. ✅ Create models
3. ✅ Create services
4. ✅ Update OdlukeIngestService
5. ⚠️ Consider migrating existing data (if any)
6. 🔄 Update any GraphRagService methods to support court decisions (if needed)
7. 🔄 Update any UI/API endpoints that reference court decisions
8. 🔄 Update tests to use new models
9. 🔄 Update documentation

## Files Created

- `/database/migrations/2025_10_17_000000_create_court_decisions_table.php`
- `/database/migrations/2025_10_17_000010_create_court_decision_document_uploads_table.php`
- `/database/migrations/2025_10_17_000020_create_court_decision_documents_table.php`
- `/app/Models/CourtDecision.php`
- `/app/Models/CourtDecisionDocument.php`
- `/app/Models/CourtDecisionDocumentUpload.php`
- `/app/Services/CourtDecisionVectorStoreService.php`

## Files Modified

- `/config/vizra-adk.php` - Added court decision table configurations
- `/app/Services/Odluke/OdlukeIngestService.php` - Completely refactored to use CourtDecision models

## Files Preserved (Unchanged)

- `/app/Models/LegalCase.php` - Still used for client cases
- `/app/Models/CaseDocument.php` - Still used for client case documents
- `/app/Models/CaseDocumentUpload.php` - Still used for client case uploads
- `/app/Services/CaseVectorStoreService.php` - Still used for client cases

