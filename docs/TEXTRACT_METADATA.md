# Textract Metadata Extraction

## Overview

The Textract Pipeline now includes a **detachable metadata extraction step** that analyzes OCR results and generates comprehensive document metadata. This metadata includes statistics, quality metrics, and content features that can be used for document analysis, quality control, and search indexing.

## Features

### Metadata Categories

The metadata extraction includes the following categories:

1. **Document Statistics**
   - Page count
   - Total lines
   - Total words
   - Total characters

2. **OCR Quality Metrics**
   - Average confidence score
   - Minimum/maximum confidence
   - Low confidence line count
   - Low confidence page detection

3. **Content Features**
   - Signature count
   - Header count
   - Bold text statistics
   - Empty page detection

4. **Per-Page Statistics**
   - Individual page metrics
   - Page-level quality scores

## Integration in Pipeline

The metadata extraction step is automatically included in the main Textract pipeline:

```
Pipeline Flow:
1. EnsureJobStep
2. DownloadDriveFileStep
3. UploadInputToS3Step
4. StartAnalysisStep
5. WaitAndFetchStep
6. SaveResultsStep
7. CollectLinesStep
8. CreateMetadataStep ← NEW STEP
9. ReconstructPdfStep
10. UploadOutputStep
11. PersistReconstructedStep
```

The metadata is automatically saved to the `textract_jobs.metadata` column as JSON.

## Standalone Usage

### 1. Using the Artisan Command

Extract metadata for a specific document:

```bash
# Display metadata summary
php artisan textract:extract-metadata {driveFileId}

# Save metadata to JSON file
php artisan textract:extract-metadata {driveFileId} --output=metadata.json

# Save metadata to TextractJob record
php artisan textract:extract-metadata {driveFileId} --save-to-job

# Pretty print JSON output
php artisan textract:extract-metadata {driveFileId} --pretty
```

**Example:**
```bash
php artisan textract:extract-metadata ABC123XYZ --output=doc-metadata.json --save-to-job
```

### 2. Using the Action Class

Extract metadata programmatically:

```php
use App\Actions\Textract\ExtractDocumentMetadata;

// Extract metadata
$metadata = ExtractDocumentMetadata::run($driveFileId);

// Extract and save to database
$metadata = ExtractDocumentMetadata::run($driveFileId, saveToJob: true);

// Dispatch as a job
ExtractDocumentMetadata::dispatch($driveFileId);

// Access metadata properties
echo "Pages: " . $metadata->pageCount;
echo "Average Confidence: " . $metadata->averageConfidence;
echo "Signatures: " . $metadata->signatureCount;

// Convert to array or JSON
$array = $metadata->toArray();
$json = $metadata->toJson();
```

### 3. Using the Service Directly

For advanced use cases:

```php
use App\Services\Ocr\DocumentMetadataExtractor;
use App\Actions\Textract\AnalyzeTextractLayout;

$extractor = app(DocumentMetadataExtractor::class);

// From OcrDocument
$ocrDocument = AnalyzeTextractLayout::run($jsonPath);
$metadata = $extractor->extract($ocrDocument, $driveFileId, $driveFileName);

// From JSON file directly
$metadata = $extractor->extractFromJson($jsonPath, $driveFileId, $driveFileName);
```

## Metadata Structure

The metadata is returned as a `DocumentMetadata` DTO and stored as JSON:

```json
{
  "document_statistics": {
    "page_count": 25,
    "total_lines": 1250,
    "total_words": 8500,
    "total_characters": 52000
  },
  "ocr_quality": {
    "average_confidence": 0.9845,
    "minimum_confidence": 0.7215,
    "maximum_confidence": 0.9998,
    "low_confidence_line_count": 12,
    "low_confidence_pages": [3, 15]
  },
  "content_features": {
    "signature_count": 4,
    "header_count": 75,
    "bold_line_count": 120,
    "bold_text_percentage": 9.6,
    "empty_pages": []
  },
  "page_statistics": [
    {
      "page_number": 1,
      "line_count": 45,
      "word_count": 312,
      "character_count": 1890,
      "signature_count": 0,
      "header_count": 3,
      "bold_line_count": 5,
      "low_confidence_line_count": 0,
      "average_confidence": 0.9912
    }
    // ... more pages
  ],
  "processing": {
    "drive_file_id": "ABC123XYZ",
    "drive_file_name": "contract.pdf",
    "timestamp": "2025-10-21T10:30:45+00:00"
  }
}
```

## Database Schema

A new `metadata` column has been added to the `textract_jobs` table:

```php
Schema::table('textract_jobs', function (Blueprint $t) {
    $t->json('metadata')->nullable();
});
```

Access stored metadata:

```php
$job = TextractJob::where('drive_file_id', $driveFileId)->first();
$pageCount = $job->metadata['document_statistics']['page_count'];
$avgConfidence = $job->metadata['ocr_quality']['average_confidence'];
```

## Quality Thresholds

The extractor uses the following quality thresholds:

- **Low Confidence Threshold**: 0.8 (80%)
  - Lines with confidence < 80% are flagged
  - Pages with average confidence < 80% are flagged

## Use Cases

1. **Quality Control**
   - Identify documents with poor OCR quality
   - Flag pages that need manual review
   - Track confidence metrics across document sets

2. **Search & Indexing**
   - Use word/character counts for search ranking
   - Identify documents with specific features (signatures, headers)
   - Filter by content characteristics

3. **Analytics**
   - Generate reports on document processing quality
   - Track OCR performance over time
   - Identify patterns in document structure

4. **Automation**
   - Route low-quality documents for human review
   - Trigger re-processing for failed pages
   - Classify documents by content features

## Running Metadata Extraction Separately

To extract metadata for documents that have already been processed:

```bash
# For a single document
php artisan textract:extract-metadata {driveFileId} --save-to-job

# Batch processing (using shell loop)
for id in $(cat drive_file_ids.txt); do
    php artisan textract:extract-metadata $id --save-to-job
done
```

Or programmatically:

```php
use App\Models\TextractJob;
use App\Actions\Textract\ExtractDocumentMetadata;

// Extract metadata for all completed jobs
$jobs = TextractJob::where('status', 'succeeded')
    ->whereNull('metadata')
    ->get();

foreach ($jobs as $job) {
    try {
        ExtractDocumentMetadata::run($job->drive_file_id, saveToJob: true);
    } catch (\Exception $e) {
        Log::error("Failed to extract metadata for {$job->drive_file_id}: {$e->getMessage()}");
    }
}
```

## Pipeline Customization

To run the pipeline without metadata extraction, create a custom pipeline:

```php
use Illuminate\Pipeline\Pipeline;

$payload = app(Pipeline::class)
    ->send($payload)
    ->through([
        EnsureJobStep::class,
        DownloadDriveFileStep::class,
        UploadInputToS3Step::class,
        StartAnalysisStep::class,
        WaitAndFetchStep::class,
        SaveResultsStep::class,
        CollectLinesStep::class,
        // CreateMetadataStep::class, ← SKIP THIS
        ReconstructPdfStep::class,
        UploadOutputStep::class,
        PersistReconstructedStep::class,
    ])
    ->thenReturn();
```

## Migration

Run the migration to add the metadata column:

```bash
php artisan migrate
```

The migration file is: `database/migrations/2025_10_21_000000_add_metadata_to_textract_jobs_table.php`

## Components

### Classes

- `App\Services\Ocr\DocumentMetadata` - DTO for metadata
- `App\Services\Ocr\DocumentMetadataExtractor` - Extraction service
- `App\Pipelines\Textract\CreateMetadataStep` - Pipeline step
- `App\Actions\Textract\ExtractDocumentMetadata` - Standalone action
- `App\Console\Commands\TextractExtractMetadata` - CLI command

### Files Created/Modified

**New Files:**
- `app/Services/Ocr/DocumentMetadata.php`
- `app/Services/Ocr/DocumentMetadataExtractor.php`
- `app/Pipelines/Textract/CreateMetadataStep.php`
- `app/Actions/Textract/ExtractDocumentMetadata.php`
- `app/Console/Commands/TextractExtractMetadata.php`
- `database/migrations/2025_10_21_000000_add_metadata_to_textract_jobs_table.php`

**Modified Files:**
- `app/Actions/Textract/ProcessDrivePdf.php` - Added CreateMetadataStep
- `app/Models/TextractJob.php` - Added metadata field

## Testing

Test the implementation:

```bash
# Test via command line
php artisan textract:extract-metadata {driveFileId}

# Test programmatically
php artisan tinker
>>> $metadata = \App\Actions\Textract\ExtractDocumentMetadata::run('{driveFileId}');
>>> $metadata->toArray();
```

## Troubleshooting

**"No TextractJob found for Drive file ID"**
- Ensure the document has been processed through the Textract pipeline first

**"Could not find Textract JSON results"**
- Check that the SaveResultsStep completed successfully
- Verify JSON files exist in `storage/app/textract/json/` or S3 `textract/json/`

**Low metadata quality**
- Review the original PDF quality
- Check Textract analysis settings
- Consider re-processing with different Textract features enabled

## Future Enhancements

Potential improvements for the metadata extraction:

- Document type classification (contract, invoice, etc.)
- Table and form detection statistics
- Language detection
- Named entity extraction summary
- Custom metadata fields
- Metadata comparison across document versions
