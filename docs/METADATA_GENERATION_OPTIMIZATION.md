# OpenAI Metadata Generation Optimization

## Problem

Previously, if `generateArticleMetadata()` were called for every article in a loop, it would cause severe performance and cost issues:

- **Time**: Law with 100 articles = 100-300 seconds (vs ~5 seconds)
- **Cost**: ~$1-10 per law ingestion (100 articles × $0.01-0.10 per call)
- **Rate Limits**: High risk of hitting OpenAI rate limits on bulk imports
- **Scalability**: Importing 10 laws = 1000+ API calls

## Solution

The implementation now calls OpenAI **ONCE per law** instead of once per article:

1. All articles are ingested first
2. A queue job `GenerateLawMetadata` is dispatched
3. The job combines all article content into a single request
4. OpenAI generates comprehensive metadata for the entire law
5. The metadata is stored in the `IngestedLaw.metadata` field

### Performance Benefits

- **1 API call per law** instead of N calls (where N = number of articles)
- **100x faster** for typical laws with 100 articles
- **100x cheaper** in OpenAI API costs
- **No rate limit issues** for bulk imports

## Architecture

### Queue Job: `GenerateLawMetadata`

**Location**: `app/Jobs/GenerateLawMetadata.php`

**Responsibilities**:
- Accepts `IngestedLaw` ID and array of articles
- Builds full law text from all articles
- Calls OpenAI once with structured prompt
- Stores enhanced metadata in JSON format

**Retry Logic**:
- 3 attempts with 60-second backoff
- 5-minute timeout per attempt
- Failed jobs are logged with error details

### Service Integration

**Location**: `app/Services/ZakonHrIngestService.php`

**Changes**:
- After ingesting all articles, `dispatchMetadataGeneration()` is called
- Articles are collected and passed to the job
- Environment-aware queue selection (see below)

### Environment-Aware Queuing

```php
// Development: Sync execution (immediate, easier debugging)
// Production: Async execution (better performance, non-blocking)
$queueConnection = app()->environment('production') ? 'database' : 'sync';
```

**Development**:
- Uses `sync` queue driver
- Executes immediately during ingestion
- Easier to debug issues
- Logs appear in real-time

**Production**:
- Uses `database` queue driver
- Executes asynchronously via queue workers
- Non-blocking ingestion
- Better resource utilization

## Generated Metadata

The job generates the following metadata fields:

```json
{
  "ai_generated": {
    "summary": "Brief summary of the law (2-3 sentences in Croatian)",
    "key_topics": ["topic1", "topic2", "topic3"],
    "practice_areas": ["area1", "area2"],
    "tags": ["tag1", "tag2", "tag3"],
    "affected_parties": ["party1", "party2"],
    "complexity_level": "basic|intermediate|advanced",
    "estimated_articles": 100,
    "openai_usage": {
      "prompt_tokens": 1500,
      "completion_tokens": 200,
      "total_tokens": 1700,
      "model": "gpt-4o-mini"
    }
  },
  "ai_generated_at": "2024-01-15T10:30:00Z"
}
```

## Usage in Production

### Running Queue Workers

For production environments, you need to run queue workers:

```bash
# Start a queue worker for metadata generation
php artisan queue:work --queue=metadata-generation

# Or use Supervisor/systemd to manage workers
```

### Monitoring

Check queue job status:

```bash
# View pending jobs
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Logs

All metadata generation is logged:

```bash
# Check logs for metadata generation
tail -f storage/logs/laravel.log | grep "GenerateLawMetadata"
```

## Testing

Unit tests are provided in `tests/Unit/Jobs/GenerateLawMetadataTest.php`:

```bash
php artisan test --filter=GenerateLawMetadataTest
```

Test coverage includes:
- Successful metadata generation
- Missing law handling
- OpenAI API errors
- Full law text construction

## Cost Estimation

**Before optimization** (if called per article):
- 100 articles × $0.05 per call = **$5.00 per law**
- 10 laws = **$50.00**

**After optimization**:
- 1 call × $0.15 per law = **$0.15 per law**
- 10 laws = **$1.50**

**Savings**: ~97% cost reduction!

## Future Enhancements

Potential improvements:

1. **Batch processing**: Generate metadata for multiple laws in one request
2. **Caching**: Skip regeneration if law hasn't changed
3. **Incremental updates**: Update only changed articles
4. **Semantic versioning**: Track metadata schema versions
5. **Quality metrics**: Score metadata quality and retry if poor

## Troubleshooting

### Job not executing in development

Check your `.env`:
```env
QUEUE_CONNECTION=sync
```

### Job stuck in production

Check queue workers are running:
```bash
ps aux | grep "queue:work"
```

Restart workers if needed:
```bash
php artisan queue:restart
```

### OpenAI rate limits

The job includes retry logic with exponential backoff. Check failed jobs:
```bash
php artisan queue:failed
```

### Missing metadata

Check if the job was dispatched:
```bash
# Check logs
grep "Dispatched metadata generation job" storage/logs/laravel.log
```

## Related Files

- `app/Jobs/GenerateLawMetadata.php` - Queue job
- `app/Services/ZakonHrIngestService.php` - Service integration
- `app/Services/OpenAIService.php` - OpenAI client
- `app/Models/IngestedLaw.php` - Law model with metadata
- `tests/Unit/Jobs/GenerateLawMetadataTest.php` - Unit tests
- `config/queue.php` - Queue configuration
- `config/openai.php` - OpenAI configuration
