# Graph Database Quick Start Guide

## 5-Minute Setup

### Step 1: Start Neo4j (Docker)

```bash
docker run \
    --name neo4j \
    -p 7474:7474 -p 7687:7687 \
    -e NEO4J_AUTH=neo4j/password123 \
    -v $HOME/neo4j/data:/data \
    -d neo4j:latest
```

Wait ~10 seconds for Neo4j to start, then verify at: http://localhost:7474

### Step 2: Configure Laravel

Add to `.env`:

```env
NEO4J_ENABLED=true
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password123
NEO4J_AUTO_SYNC=true
```

### Step 3: Initialize Schema

```bash
php artisan graph:init
```

Expected output:
```
Initializing Neo4j graph database...
Creating constraints and indexes...
✓ Schema initialized
Creating tag hierarchy...
✓ Tag hierarchy created

Graph database initialized successfully!

Next steps:
1. Run: php artisan graph:sync --all
2. Or sync specific types: php artisan graph:sync --laws
```

### Step 4: Sync Existing Data

```bash
# Sync all data (this may take a while)
php artisan graph:sync --all

# OR sync incrementally
php artisan graph:sync --laws
php artisan graph:sync --cases
```

### Step 5: Verify Installation

```bash
php artisan graph:stats
```

Expected output:
```
Graph Database Statistics

Node Counts:
  LawDocument: 150
  CaseDocument: 82
  Keyword: 421
  Tag: 156
  Jurisdiction: 5
  Court: 12

Relationship Counts:
  HAS_TAG: 534
  HAS_KEYWORD: 1678
  SIMILAR_TO: 341
  BELONGS_TO_JURISDICTION: 232

Top Tags:
  civil_law: 56 documents
  criminal_law: 32 documents
```

## Quick Test

### Test 1: Query from Command Line

```bash
php artisan graph:query "ugovorno pravo" --type=law --limit=5
```

### Test 2: Use in PHP Code

```php
use App\Services\GraphRagService;

$graphRag = app(GraphRagService::class);

// Enhanced query with graph context
$results = $graphRag->enhancedQuery('contract law', 'both', 10);

// Get context for specific document
$context = $graphRag->getGraphContext('LawDocument', $lawId);
```

### Test 3: Use Helper Queries

```php
use App\Services\GraphQueryHelper;

$helper = app(GraphQueryHelper::class);

// Find similar documents
$recommendations = $helper->recommendDocuments($docId, 10);

// Find by jurisdiction and tags
$filtered = $helper->findByJurisdictionAndTags('Croatia', ['civil_law', 'contracts']);

// Find influential documents
$influential = $helper->findInfluentialDocuments('LawDocument', 20);
```

## Integration with Existing Code

### Automatic Sync on New Documents

The graph database automatically syncs when you ingest new documents:

```php
use App\Services\LawVectorStoreService;

$lawVectorStore = app(LawVectorStoreService::class);

// This will automatically sync to graph if NEO4J_AUTO_SYNC=true
$result = $lawVectorStore->ingest($docId, $docs, $options);
```

### Disable Auto-Sync (Optional)

If you want to sync manually later:

```env
NEO4J_AUTO_SYNC=false
```

Then sync manually:

```bash
php artisan graph:sync --laws
```

## Common Use Cases

### Use Case 1: Find Related Laws by Topic

```php
use App\Services\GraphQueryHelper;

$helper = app(GraphQueryHelper::class);
$relatedLaws = $helper->findTopicCluster('civil_law');
```

### Use Case 2: Get Comprehensive Document Context

```php
use App\Services\GraphRagService;

$graphRag = app(GraphRagService::class);
$context = $graphRag->getGraphContext('LawDocument', $lawId);

// Access different parts
$tags = $context['tags'];          // Hierarchical tags
$keywords = $context['keywords'];  // Weighted keywords
$similar = $context['similar'];    // Similar docs by embedding
$related = $context['related'];    // Related via shared tags
```

### Use Case 3: Enhanced RAG Pipeline

```php
use App\Services\GraphRagService;
use App\Services\LawVectorStoreService;

// 1. Vector search (existing)
$vectorResults = searchVectorDB($query);

// 2. Enhance with graph context
$graphRag = app(GraphRagService::class);

foreach ($vectorResults as &$result) {
    $context = $graphRag->getGraphContext('LawDocument', $result['id']);
    $result['graph_context'] = $context;
}

// 3. Also get graph-based matches
$graphMatches = $graphRag->enhancedQuery($query, 'law', 20);

// 4. Merge and rank results
$finalResults = array_merge($vectorResults, $graphMatches['related_via_keywords']);
```

### Use Case 4: Tag Management

```php
use App\Services\TaggingService;

$tagging = app(TaggingService::class);

// Apply tags
$tagging->applyTag('LawDocument', $lawId, 'civil_law');
$tagging->applyTag('LawDocument', $lawId, 'contracts');

// Get all tags for a document
$tags = $tagging->getNodeTags('LawDocument', $lawId);

// Find related tags
$relatedTags = $tagging->getRelatedTags('civil_law', 10);

// Get tag hierarchy
$hierarchy = $tagging->getTagHierarchy('contracts');
// Returns: ['contracts', 'civil_law', 'legal_area']
```

## Troubleshooting

### Neo4j Not Connecting

```bash
# Check if Neo4j is running
docker ps | grep neo4j

# Check logs
docker logs neo4j

# Restart Neo4j
docker restart neo4j
```

### Sync Errors

Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

### Performance Issues

Increase Neo4j memory:
```bash
docker run \
    --name neo4j \
    -e NEO4J_dbms_memory_heap_max__size=4G \
    -e NEO4J_dbms_memory_pagecache_size=2G \
    # ... other options
```

### Clear Graph Database

⚠️ **WARNING: This deletes all graph data!**

```php
use App\Services\GraphDatabaseService;

$graph = app(GraphDatabaseService::class);
$graph->clearAll();
```

Then re-initialize:
```bash
php artisan graph:init
php artisan graph:sync --all
```

## Performance Tips

1. **Batch Sync**: Use `graph:sync --all` for initial sync
2. **Auto-Sync**: Enable `NEO4J_AUTO_SYNC=true` for new documents
3. **Indexes**: Schema automatically creates optimal indexes
4. **Similarity Threshold**: Adjust `NEO4J_SIMILARITY_THRESHOLD` (default: 0.85)
5. **Batch Size**: Adjust `NEO4J_SYNC_BATCH_SIZE` (default: 100)

## Next Steps

1. ✅ Read the full documentation: `GRAPH_DATABASE_README.md`
2. ✅ Review example code: `app/Examples/GraphRagExamples.php`
3. ✅ Explore Neo4j Browser: http://localhost:7474
4. ✅ Write custom Cypher queries using `GraphDatabaseService`
5. ✅ Implement citation extraction for CITES relationships
6. ✅ Add custom tags for your specific legal domain

## Support

- Neo4j Documentation: https://neo4j.com/docs/
- Laudis Neo4j PHP Client: https://github.com/neo4j-php/neo4j-php-client
- Cypher Query Language: https://neo4j.com/docs/cypher-manual/

## Useful Cypher Queries

### View All Node Types
```cypher
MATCH (n)
RETURN DISTINCT labels(n), count(n)
ORDER BY count(n) DESC
```

### View All Relationship Types
```cypher
MATCH ()-[r]->()
RETURN DISTINCT type(r), count(r)
ORDER BY count(r) DESC
```

### Find Highly Connected Documents
```cypher
MATCH (doc)-[r]-()
WITH doc, count(r) as connections
ORDER BY connections DESC
LIMIT 10
RETURN doc.title, doc.law_number, connections
```

### Find Documents with Most Tags
```cypher
MATCH (doc)-[:HAS_TAG]->(tag)
WITH doc, collect(tag.name) as tags, count(tag) as tag_count
ORDER BY tag_count DESC
LIMIT 10
RETURN doc.title, tags, tag_count
```

### Visualize Keyword Network
```cypher
MATCH (k:Keyword)<-[:HAS_KEYWORD]-(doc)-[:HAS_KEYWORD]->(related:Keyword)
WHERE k.name = 'contract'
RETURN k, doc, related
LIMIT 50
```

