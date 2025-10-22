# Textract Legal Metadata Extraction

## Overview

The Textract Pipeline includes a **detachable legal metadata extraction step** that analyzes OCR results and extracts comprehensive legal document metadata. This metadata includes legal citations, entities (courts, parties), document classification, dates, and key legal phrases tailored for Croatian legal documents.

## Features

### Metadata Categories

The legal metadata extraction includes the following categories:

1. **Legal Citations**
   - Statute citations (e.g., ZPP čl. 110 st. 2)
   - Case number references (e.g., Rev 123/2024)
   - ECLI identifiers
   - Narodne Novine references (official gazette)
   - Referenced laws (unique list)

2. **Legal Entities**
   - Courts mentioned (e.g., Vrhovni sud RH, Županijski sud u Zagrebu)
   - Parties (plaintiffs, defendants, with role detection)
   - Dates referenced in the document

3. **Document Classification**
   - Document type (judgment, decision, motion, contract, etc.)
   - Jurisdiction (civil, criminal, administrative, etc.)
   - Classification confidence score

4. **Content Analysis**
   - Key legal phrases
   - Word count, paragraph count
   - OCR quality metrics (lightweight)

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
8. CreateMetadataStep ← LEGAL METADATA EXTRACTION
9. ReconstructPdfStep
10. UploadOutputStep
11. PersistReconstructedStep
```

The metadata is automatically saved to the `textract_jobs.metadata` column as JSON.

## Standalone Usage

### 1. Using the Artisan Command

Extract legal metadata for a specific document:

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
echo "Document Type: " . $metadata->documentType;
echo "Total Citations: " . $metadata->totalCitations;
echo "Courts: " . count($metadata->courts);
echo "Parties: " . count($metadata->parties);

// Convert to array or JSON
$array = $metadata->toArray();
$json = $metadata->toJson();

// Get summary
$summary = $metadata->getSummary();
```

### 3. Using the Service Directly

For advanced use cases:

```php
use App\Services\Ocr\LegalMetadataExtractor;
use App\Actions\Textract\AnalyzeTextractLayout;

$extractor = app(LegalMetadataExtractor::class);

// From OcrDocument
$ocrDocument = AnalyzeTextractLayout::run($jsonPath);
$metadata = $extractor->extract($ocrDocument, $driveFileId, $driveFileName);

// From JSON file directly
$metadata = $extractor->extractFromJson($jsonPath, $driveFileId, $driveFileName);
```

## Metadata Structure

The metadata is returned as a `LegalDocumentMetadata` DTO and stored as JSON. See example in the documentation below.

## Legal Detectors

### 1. HrLegalCitationsDetector

Uses the existing Croatian legal citations detector to extract:
- **Statute citations**: ZPP čl. 110, OZ čl. 1046 st. 2, etc.
- **Case numbers**: Rev 123/2024, Gž 456/2023, U-III-1234/2019, etc.
- **ECLI identifiers**: European Case Law Identifier
- **Narodne Novine references**: NN 53/91, NN 112/12, etc.
- **Dates**: Various date formats in Croatian

### 2. CourtDetector

Detects Croatian courts mentioned in documents:
- Supreme Court (Vrhovni sud RH)
- Constitutional Court (Ustavni sud RH)
- High courts (Visoki trgovački sud, Visoki upravni sud, etc.)
- County courts (Županijski sud u Zagrebu, etc.)
- Municipal courts (Općinski sud)
- Commercial courts (Trgovački sud)
- Misdemeanor courts (Prekršajni sud)

### 3. PartyDetector

Extracts legal parties with role detection:
- **Plaintiffs** (tužitelj/tužiteljica)
- **Defendants** (tuženi/tužena)
- **Accused** (optuženi/okrivljenik)
- **Appellants** (žalitelj)
- **Applicants** (podnositelj)
- **Attorneys** (odvjetnik/branitelj)
- **Witnesses** (svjedok)
- **Experts** (vještak)

Also detects entity type (person vs. organization) based on:
- Company suffixes (d.o.o., d.d., obrt, etc.)
- Organizational keywords

### 4. DocumentTypeClassifier

Classifies documents into types with confidence scores:

**Document Types:**
- **judgment** (presuda)
- **decision** (rješenje)
- **ruling** (nalog/naredba)
- **motion** (zahtjev/prijedlog)
- **complaint** (žalba)
- **appeal** (revizija)
- **indictment** (optužnica)
- **contract** (ugovor)
- **power_of_attorney** (punomoć)
- **statement** (izjava)
- **certificate** (potvrda/uvjerenje)
- **statute** (zakon)
- **ordinance** (pravilnik/uredba)

**Jurisdictions:**
- **civil** (građanski)
- **criminal** (kazneni)
- **administrative** (upravni)
- **commercial** (trgovački)
- **labor** (radni)
- **family** (obiteljski)
- **constitutional** (ustavni)

## Components

### Classes

**DTOs:**
- `App\Services\Ocr\LegalDocumentMetadata` - DTO for legal metadata

**Services:**
- `App\Services\Ocr\LegalMetadataExtractor` - Main extraction orchestrator
- `App\Services\HrLegalCitationsDetector` - Croatian legal citations detector
- `App\Services\LegalMetadata\CourtDetector` - Court name detector
- `App\Services\LegalMetadata\PartyDetector` - Party/entity extractor
- `App\Services\LegalMetadata\DocumentTypeClassifier` - Document classification

**Pipeline & Actions:**
- `App\Pipelines\Textract\CreateMetadataStep` - Pipeline step
- `App\Actions\Textract\ExtractDocumentMetadata` - Standalone action
- `App\Console\Commands\TextractExtractMetadata` - CLI command

## Testing

Test the implementation:

```bash
# Test via command line
php artisan textract:extract-metadata {driveFileId}

# Test programmatically
php artisan tinker
>>> $metadata = \App\Actions\Textract\ExtractDocumentMetadata::run('{driveFileId}');
>>> $metadata->getSummary();
>>> $metadata->toArray();
```
