# Milestone B: Laws Pipeline Hardening - Implementation Details

This document describes the improvements made in Milestone B to harden the laws processing pipeline.

## Overview

Milestone B introduces comprehensive improvements to handle edge cases, ensure data consistency, enable metadata backfilling, and add robust retry logic throughout the pipeline.

## Task 6: Article Splitter Edge Cases

### Improvements
- **Comprehensive edge case handling** for article number formats:
  - Standard: `Članak 24.`
  - Uppercase: `CLANAK 24.`
  - Lettered variants: `Članak 24a`, `Članak 24.a`, `Članak 24. a)`
  - With delimiters: `Članak 24)`, `Članak 24(`

- **Special handling**:
  - Non-breaking spaces (U+00A0) are normalized
  - NN markers like `(NN 123/20)` are preserved in article body
  - Lettered articles (24a, 24b) are automatically merged with base article (24)
  - Nested HTML tags and missing `<body>` tags are handled gracefully

### Test Coverage
Created `tests/Unit/LawParserEdgeCasesTest.php` with 18 comprehensive test cases covering:
- Dotted letters with parentheses
- Uppercase formats
- Missing body tags
- Nested wrappers
- Non-breaking spaces
- NN marker preservation
- Lettered article merging
- Preamble and trailing text handling

## Task 7: Consistent Chunk Metadata Mapping

### Improvements
- **Uniform metadata structure** across all chunks:
  - `law_number`: Consistent law identification
  - `version`: Always set (e.g., "consolidated")
  - `promulgation_date`: Publication date
  - `effective_date`: Effective date (added)
  - `heading_chain`: Structural hierarchy preserved
  - `tags`: Properly formatted as JSON arrays

- **Enhanced `mapLawColumns()`**:
  - Ensures tags are always stored as JSON arrays
  - Validates date fields are present when supplied
  - Filters empty array values
  - Improved field documentation

### Files Modified
- `app/Services/LawIngestService.php`: Enhanced metadata building
- `app/Services/LawVectorStoreService.php`: Improved column mapping

## Task 8: Backfill AI Metadata Command

### New Command: `laws:regen-metadata`

Generate AI metadata for existing laws that lack it.

#### Usage Examples

```bash
# Preview what would be processed
php artisan laws:regen-metadata --dry-run

# Process specific law
php artisan laws:regen-metadata --doc-id=nn-2021-12-1234

# Process with custom batch size and rate limit
php artisan laws:regen-metadata --batch-size=5 --rate-limit=30

# Force regeneration even if metadata exists
php artisan laws:regen-metadata --force

# Process limited number (for testing)
php artisan laws:regen-metadata --limit=10 --dry-run
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--dry-run` | Preview without dispatching jobs | false |
| `--doc-id=ID` | Filter by specific document ID | none |
| `--batch-size=N` | Laws per batch | 10 |
| `--rate-limit=N` | Seconds between batches | 60 |
| `--force` | Regenerate existing metadata | false |
| `--limit=N` | Maximum laws to process | none |

#### Features
- **Batch processing** with progress tracking
- **Rate limiting** to avoid API throttling
- **Validation** of command options
- **Comprehensive logging** of all operations
- **Success rate reporting** in summary
- **Error handling** with detailed logging

### Files Created
- `app/Console/Commands/LawsRegenMetadata.php`

## Task 9: Rate Limiting and Retries

### Retry Strategy

All HTTP and API calls now implement **exponential backoff with jitter**:

```
Formula: base_delay * 2^(attempt-1) + random_jitter
```

**Example delays:**
- Attempt 1: 1000ms + jitter (1000-1500ms)
- Attempt 2: 2000ms + jitter (2000-3000ms)
- Attempt 3: 4000ms + jitter (4000-6000ms)

**Why jitter?**
Randomized delays prevent the "thundering herd" problem when multiple processes retry simultaneously.

### Configuration

Retry behavior is configurable via `config/services.php`:

```php
'law_ingest' => [
    'retry_base_delay' => 1000,        // Base delay in ms
    'retry_jitter_percent' => 0.5,     // 50% jitter
],

'embeddings' => [
    'retry_base_delay' => 1000,
    'retry_jitter_percent' => 0.5,
],

'zakon_hr_scraper' => [
    'retry_base_delay' => 1000,
    'retry_jitter_percent' => 0.5,
],
```

### Enhanced Services

#### ZakonHrScraper.php
- `scrapeCategoryPage()`: Retries with exponential backoff
- `scrapeLawContent()`: Retries with exponential backoff
- **Structured logging**: URL, context, attempt count, errors

#### LawIngestService.php
- `fetchWithRetry()`: Generic retry wrapper for HTTP calls
- **HTML fetching**: 3 retry attempts with backoff
- **PDF downloads**: 3 retry attempts with backoff + content-type validation
- **Structured logging**: URL, context, PDF detection, errors

#### LawVectorStoreService.php
- `callEmbeddingsWithRetry()`: Retry wrapper for embeddings API
- **Duration tracking**: Monitors API performance
- **Structured logging**: Document ID, input count, model, duration, errors

### Logging Structure

All retry operations log:
- **Warning on failure**: Attempt number, max retries, error message
- **Info on retry success**: Which attempt succeeded
- **Error on exhaustion**: Total attempts, final error
- **Debug on delay**: Next attempt number, delay duration

Example log entry:
```json
{
  "level": "warning",
  "message": "HTTP fetch failed",
  "context": {
    "url": "https://example.com/law.html",
    "context": "law_html",
    "attempt": 1,
    "max_retries": 3,
    "error": "Connection timeout"
  }
}
```

## Files Modified Summary

### Modified
- `app/Services/LawParser.php`
  - Improved regex patterns
  - Added input validation
  - Enhanced documentation

- `app/Services/LawIngestService.php`
  - Retry logic for HTTP/PDF fetches
  - Configurable backoff parameters
  - Enhanced metadata structure

- `app/Services/LawVectorStoreService.php`
  - Retry logic for embeddings API
  - Duration tracking
  - Improved metadata mapping

- `app/Services/ZakonHrScraper.php`
  - Retry logic for scraping
  - Configurable backoff parameters

### Created
- `app/Console/Commands/LawsRegenMetadata.php`
  - Metadata backfilling command

- `tests/Unit/LawParserEdgeCasesTest.php`
  - Comprehensive edge case tests

## Testing

### Running Tests
```bash
# Run edge case tests
php artisan test --filter=LawParserEdgeCasesTest

# Run all tests
php artisan test
```

### Manual Testing

1. **Test retry logic**: Temporarily break network to see retry behavior in logs
2. **Test metadata command**: Use `--dry-run` first to preview
3. **Test article parsing**: Process sample laws with various article formats

## Performance Considerations

### Retry Impact
- Each retry adds delay (1-6 seconds typical)
- 3 attempts max means 7-15 seconds worst case per request
- Rate limiting in metadata command prevents API overwhelm

### Optimization Tips
1. **Batch size**: Larger batches = fewer DB queries but longer transactions
2. **Rate limit**: Balance between speed and API limits
3. **Concurrent processing**: Use queue workers for parallel execution

## Monitoring

### Key Metrics to Track
- Retry success rate per service
- Average retry attempts before success
- API call durations (embedding, HTTP)
- Failed job counts
- Metadata generation coverage

### Log Analysis
```bash
# Find retry successes
grep "succeeded after retry" storage/logs/laravel.log

# Find exhausted retries
grep "failed after all retries" storage/logs/laravel.log

# Track metadata generation
grep "Laws metadata regeneration" storage/logs/laravel.log
```

## Future Improvements

- [ ] Add circuit breaker pattern for cascading failures
- [ ] Implement request rate limiting (not just retry backoff)
- [ ] Add health check endpoints for monitoring
- [ ] Create metrics dashboard for retry statistics
- [ ] Add support for webhook notifications on failures
- [ ] Implement distributed tracing for debugging

## References

- **Exponential Backoff**: [AWS Architecture Blog](https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/)
- **Retry Pattern**: [Microsoft Cloud Design Patterns](https://docs.microsoft.com/en-us/azure/architecture/patterns/retry)
- **OpenAI Rate Limits**: [OpenAI Documentation](https://platform.openai.com/docs/guides/rate-limits)
