# Milestone A: Case Documents Pipeline - Implementation Summary

## Overview
This milestone implements an end-to-end case document ingestion pipeline with OCR quality gates, language normalization, chunking, and embedding generation.

## Components Implemented

### 1. Core Services

#### `app/Services/CaseIngestPipeline.php`
Main orchestrator for case document ingestion. Handles:
- **OCR Quality Validation**: Checks confidence and coverage metrics
- **Language Normalization**: Croatian text cleanup via HrLanguageNormalizer
- **Text Chunking**: Smart sliding window with sentence boundary detection
- **Embedding Generation**: Integrates with CaseVectorStoreService
- **Quality Gates**: Blocks embedding for low-quality OCR (configurable threshold)

**Key Methods:**
- `ingest(caseId, docId, rawText, ocrBlocks, options)` - Main entry point
- `needsReOcr(qualityMetrics, options)` - Determines if re-OCR is needed

**Configuration:**
- `min_confidence`: Default 0.82 (82%)
- `min_coverage`: Default 0.75 (75%)
- `chunk_size`: Default 1200 characters
- `overlap`: Default 150 characters

#### `app/Services/Ocr/OcrQualityAnalyzer.php`
Analyzes OCR quality from AWS Textract blocks or raw text.

**Features:**
- Confidence calculation from Textract WORD blocks
- Per-page quality analysis
- Low-confidence page detection
- Text-based quality estimation (heuristics)

**Metrics Returned:**
- `confidence`: Overall confidence (0.0-1.0)
- `coverage`: Ratio of words with confidence data
- `total_words`: Word count
- `low_confidence_pages`: Count of pages below threshold
- `page_stats`: Per-page breakdown

#### `app/Services/Ocr/HrLanguageNormalizer.php`
Croatian language text normalization for OCR output.

**Handles:**
- Ligature fixes (fi, fl, ffi, etc.)
- Hyphenation repair (words split across lines)
- Croatian diacritics (č, ć, ž, š, đ)
- Quote normalization
- Whitespace cleanup
- Unicode normalization (NFC form)
- Invisible character removal (soft hyphens, zero-width spaces)

### 2. Pipeline Steps

#### `app/Pipelines/Textract/CheckOcrQualityStep.php`
New pipeline step inserted into ProcessDrivePdf workflow.

**Responsibilities:**
- Analyzes OCR quality from Textract blocks
- Adds quality metrics to payload
- Sets `needsReview` flag if quality is below thresholds
- Updates TextractJob metadata with quality information

**Configuration Keys:**
- `vizra-adk.ocr.min_confidence`
- `vizra-adk.ocr.min_coverage`
- `vizra-adk.ocr.max_low_confidence_pages`

#### Updated: `app/Pipelines/Textract/PersistReconstructedStep.php`
Refactored to use CaseIngestPipeline instead of inline chunking.

**Changes:**
- Removed inline chunking and embedding logic
- Delegates to `CaseIngestPipeline::ingest()`
- Passes OCR quality metrics from CheckOcrQualityStep
- Fallback to direct save if pipeline fails

#### Updated: `app/Actions/Textract/ProcessDrivePdf.php`
Added CheckOcrQualityStep to the pipeline sequence.

**New Pipeline Order:**
1. EnsureJobStep
2. DownloadDriveFileStep
3. UploadInputToS3Step
4. StartAnalysisStep
5. WaitAndFetchStep
6. SaveResultsStep
7. CollectLinesStep
8. **CheckOcrQualityStep** ← NEW
9. CreateMetadataStep
10. ReconstructPdfStep
11. UploadOutputStep
12. PersistReconstructedStep

### 3. CLI Command

#### `app/Console/Commands/CasesIngest.php`
Batch ingestion command for processing directories of PDF files.

**Usage:**
```bash
php artisan cases:ingest --path=/path/to/pdfs --case=<case-ulid>
php artisan cases:ingest --path=/path/to/pdfs --case=<case-ulid> --chunk=1200 --overlap=150
php artisan cases:ingest --path=/path/to/pdfs --case=<case-ulid> --local-ocr
php artisan cases:ingest --path=/path/to/pdfs --case=<case-ulid> --skip-existing --dry-run
```

**Options:**
- `--path`: Directory containing PDF files (required)
- `--case`: ULID of legal case (required)
- `--chunk`: Chunk size (default: 1200)
- `--overlap`: Chunk overlap (default: 150)
- `--ocr`: Force Textract OCR (not yet implemented)
- `--local-ocr`: Use local tesseract OCR
- `--skip-existing`: Skip files already in database
- `--dry-run`: Preview without processing

**Features:**
- Recursive directory scanning
- SHA256-based deduplication
- Progress bar with statistics
- Verbose mode for detailed output
- Error handling and reporting

### 4. Tests

#### `tests/Feature/CaseIngestFlowTest.php`
End-to-end feature tests covering the full pipeline.

**Test Cases:**
- ✅ Complete ingestion pipeline (upload → OCR → chunk → embed → searchable)
- ✅ Quality gates block embedding on low OCR confidence
- ✅ Upload record creation and linking
- ✅ Content hash deduplication
- ✅ Document searchability after ingestion

#### `tests/Unit/OcrConfidenceTest.php`
Unit tests for OCR quality analysis.

**Test Cases:**
- ✅ Confidence calculation from Textract blocks
- ✅ Low-confidence page identification
- ✅ Handling blocks without confidence data
- ✅ Quality estimation from raw text
- ✅ Poor quality text detection
- ✅ Empty text handling
- ✅ Per-page statistics
- ✅ Coverage calculation

#### `tests/Unit/CaseChunkerTest.php`
Unit tests for text chunking logic.

**Test Cases:**
- ✅ Chunking with specified size and overlap
- ✅ Overlapping chunk creation
- ✅ Single chunk for small text
- ✅ Empty array for empty text
- ✅ Sentence boundary detection
- ✅ Multibyte Croatian character handling
- ✅ Empty chunk filtering
- ✅ Paragraph break preservation
- ✅ Chunk size limit enforcement
- ✅ Small chunk size handling

## Integration Points

### Database Schema
Uses existing tables (no migrations needed):
- `cases`: Legal case records
- `cases_documents`: Chunked documents with embeddings
- `cases_documents_uploads`: Upload tracking
- `textract_jobs`: OCR job tracking with metadata

### Configuration
New config keys (all have sensible defaults):
- `vizra-adk.ocr.min_confidence`: 0.82
- `vizra-adk.ocr.min_coverage`: 0.75
- `vizra-adk.ocr.max_low_confidence_pages`: 3
- `vizra-adk.ocr.skip_embedding_on_low_quality`: false

Existing config keys used:
- `vizra-adk.vector_memory.chunking.chunk_size`: 1200
- `vizra-adk.vector_memory.chunking.overlap`: 150
- `vizra-adk.vector_memory.embedding_provider`: 'openai'
- `vizra-adk.vector_memory.embedding_models.openai`: 'text-embedding-3-small'

### Service Dependencies
All services use Laravel's automatic dependency injection:
- `CaseIngestPipeline` → `OcrQualityAnalyzer`, `HrLanguageNormalizer`, `CaseVectorStoreService`
- `CheckOcrQualityStep` → `OcrQualityAnalyzer`
- `PersistReconstructedStep` → `LegalMetadataExtractor`, `CaseIngestPipeline`

## Workflow Examples

### Google Drive PDF Processing
```
User uploads PDF to Google Drive
    ↓
TextractManager: syncFromDrive()
    ↓
ProcessDrivePdf::dispatch()
    ↓
Pipeline: Download → S3 Upload → Textract Analysis
    ↓
CheckOcrQualityStep: Analyze confidence & coverage
    ↓
CreateMetadataStep: Extract legal metadata
    ↓
ReconstructPdfStep: Create searchable PDF
    ↓
PersistReconstructedStep:
    ├─ Create CaseDocumentUpload
    ├─ CaseIngestPipeline::ingest()
    │   ├─ Quality check (pass/fail)
    │   ├─ Croatian normalization
    │   ├─ Smart chunking
    │   └─ Embedding generation
    └─ CaseDocument records created
```

### CLI Batch Processing
```
php artisan cases:ingest --path=/data/pdfs --case=<case-id>
    ↓
Scan directory for PDFs
    ↓
For each PDF:
    ├─ Create CaseDocumentUpload
    ├─ Extract text (local OCR or pdftotext)
    ├─ CaseIngestPipeline::ingest()
    │   ├─ Quality estimation from text
    │   ├─ Croatian normalization
    │   ├─ Smart chunking
    │   └─ Embedding generation
    └─ CaseDocument records created
    ↓
Print summary statistics
```

## OCR Quality Gates

### Quality Check Process
1. **Analyze blocks** (if available from Textract)
   - Calculate average confidence per word
   - Identify low-confidence pages
   - Compute coverage (% of words with confidence)

2. **Apply thresholds**
   - Default minimum confidence: 82%
   - Default minimum coverage: 75%
   - Default max low-confidence pages: 3

3. **Decision**
   - **PASS**: Proceed with embedding
   - **FAIL**: Mark `needs_review`, optionally skip embedding

4. **Metadata storage**
   - Quality metrics stored in `TextractJob.metadata.ocrQuality`
   - Review flag stored in `TextractJob.metadata.needsReview`
   - Reasons stored in `TextractJob.metadata.reviewReasons`

### Re-OCR Path
To re-process a document:
1. Identify low-quality documents via `needsReview` flag
2. Manually review OCR output
3. If needed, re-run Textract or use different OCR tool
4. Re-ingest with new text

## Language Normalization

### Croatian-Specific Fixes
1. **Ligatures**: ﬁ → fi, ﬂ → fl, ﬀ → ff
2. **Hyphenation**: "dje-\nlatnik" → "djelatnik"
3. **Diacritics**: Ensure NFC form for č, ć, ž, š, đ
4. **Digit confusion**: Fix '1' vs 'l', '0' vs 'O' in context
5. **Quotes**: Normalize smart quotes to straight quotes
6. **Whitespace**: Collapse spaces, normalize line endings
7. **Invisible chars**: Remove soft hyphens, zero-width spaces

## Metadata Schema

### CaseDocument.metadata (JSON)
```json
{
  "drive_file_id": "1abc...",
  "drive_file_name": "decision.pdf",
  "s3_input_key": "textract/input/...",
  "s3_json_key": "textract/json/...",
  "s3_output_key": "textract/output/...",
  "local_json_path": "textract/json/...",
  "local_pdf_path": "cases/...",
  "source": "drive-textract",
  "ocr_quality": {
    "confidence": 0.9234,
    "coverage": 0.9876,
    "total_words": 1245,
    "low_confidence_pages": 0
  },
  "quality_check": { ... },
  "normalized": true,
  "chunk_size": 1200,
  "overlap": 150
}
```

### CaseDocument.actual (JSON)
Legal metadata from LegalMetadataExtractor:
```json
{
  "driveFileId": "1abc...",
  "driveFileName": "decision.pdf",
  "documentType": "decision",
  "jurisdiction": "municipal_court",
  "confidence": 0.85,
  "courts": ["Općinski sud u Zagrebu"],
  "parties": ["Ivan Kovač", "Petar Novak"],
  "statuteCitations": [...],
  "caseNumberCitations": [...],
  "keyPhrases": [...],
  "pageCount": 5,
  "wordCount": 1245,
  "averageConfidence": 0.9234
}
```

## Next Steps / Future Enhancements

1. **UI Enhancements**
   - Add metadata validation UI in TextractManager
   - Display OCR quality metrics in job detail view
   - Add "Re-OCR" button for low-quality documents

2. **Re-OCR Automation**
   - Automatic re-OCR queue for failed documents
   - Alternative OCR providers (Google Vision, Azure)
   - Human-in-the-loop review workflow

3. **Advanced Chunking**
   - Semantic chunking based on document structure
   - Legal section detection (Članak, Stavak)
   - Table and form extraction

4. **Quality Improvements**
   - ML-based quality prediction
   - Training data collection for Croatian legal text
   - Custom OCR models for legal documents

5. **Performance**
   - Batch embedding generation
   - Async job processing improvements
   - Caching for repeated documents

## Files Changed/Created

### New Files (8)
- `app/Services/CaseIngestPipeline.php`
- `app/Services/Ocr/OcrQualityAnalyzer.php`
- `app/Services/Ocr/HrLanguageNormalizer.php`
- `app/Pipelines/Textract/CheckOcrQualityStep.php`
- `app/Console/Commands/CasesIngest.php`
- `tests/Feature/CaseIngestFlowTest.php`
- `tests/Unit/OcrConfidenceTest.php`
- `tests/Unit/CaseChunkerTest.php`

### Modified Files (2)
- `app/Actions/Textract/ProcessDrivePdf.php`
- `app/Pipelines/Textract/PersistReconstructedStep.php`

## Testing

Run tests:
```bash
# Feature tests
php artisan test tests/Feature/CaseIngestFlowTest.php

# Unit tests
php artisan test tests/Unit/OcrConfidenceTest.php
php artisan test tests/Unit/CaseChunkerTest.php

# All case-related tests
php artisan test --filter=Case
```

## Configuration Setup

Add to `config/vizra-adk.php` (optional, defaults provided):
```php
'ocr' => [
    'min_confidence' => env('OCR_MIN_CONFIDENCE', 0.82),
    'min_coverage' => env('OCR_MIN_COVERAGE', 0.75),
    'max_low_confidence_pages' => env('OCR_MAX_LOW_CONF_PAGES', 3),
    'skip_embedding_on_low_quality' => env('OCR_SKIP_EMBED_LOW_QUALITY', false),
],
```

## Deployment Notes

1. No database migrations required (uses existing schema)
2. No new environment variables required (all have defaults)
3. Compatible with existing TextractManager workflow
4. Backward compatible (doesn't break existing functionality)
5. Services auto-registered via Laravel container

---

**Implementation Date**: 2025-10-25
**Milestone**: A - Case Documents Pipeline
**Status**: ✅ Complete
