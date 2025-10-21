# Graph Database Integration for RAG System

## Overview

This implementation integrates Neo4j graph database into the existing RAG (Retrieval-Augmented Generation) system to enhance legal document retrieval through relationship mapping, hierarchical tagging, and semantic connections.

## Architecture

### Components

1. **GraphDatabaseService** - Core service for Neo4j operations
2. **TaggingService** - Hierarchical tagging system for legal content
3. **GraphRagService** - Integration layer between vector embeddings and graph relationships
4. **Artisan Commands** - CLI tools for management

### Graph Schema

#### Node Types
- **LawDocument** - Individual law chunks with embeddings
- **CaseDocument** - Court case decision chunks
- **Keyword** - Extracted keywords from content
- **Tag** - Hierarchical tags for categorization
- **Jurisdiction** - Geographic/legal jurisdictions
- **Court** - Court entities
- **Topic** - Legal topics and concepts
- **LegalConcept** - Abstract legal concepts

#### Relationship Types
- **HAS_TAG** - Document to tag relationship
- **HAS_KEYWORD** - Document to keyword with weight
- **SIMILAR_TO** - Similarity based on embeddings
- **BELONGS_TO_JURISDICTION** - Document to jurisdiction
- **CITES** - Citation relationships
- **REFERENCES** - Reference relationships
- **PARENT_TAG** - Tag hierarchy
- **RELATES_TO** - Generic relationship

## Installation

### 1. Install Neo4j

```bash
# Docker (recommended)
docker run \
    --name neo4j \
    -p 7474:7474 -p 7687:7687 \
    -e NEO4J_AUTH=neo4j/your_password \
    -v $HOME/neo4j/data:/data \
    -d neo4j:latest
```

Or install natively from https://neo4j.com/download/

### 2. Configure Environment

Add to your `.env` file:

```env
NEO4J_ENABLED=true
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_HTTP_PORT=7474
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password
NEO4J_DATABASE=neo4j
NEO4J_CONNECTION=bolt

# Sync settings
NEO4J_AUTO_SYNC=true
NEO4J_SYNC_BATCH_SIZE=100
NEO4J_SIMILARITY_THRESHOLD=0.85
```

### 3. Initialize Graph Schema

```bash
php artisan graph:init
```

This creates:
- Constraints for unique IDs
- Indexes for performance
- Tag hierarchy structure

### 4. Sync Existing Data

```bash
# Sync all data
php artisan graph:sync --all

# Or sync specific types
php artisan graph:sync --laws
php artisan graph:sync --cases
```

## Tagging Strategy

### Hierarchical Tag Categories

The system implements a multi-level tagging hierarchy:

#### 1. Legal Area Tags
```
legal_area/
├── civil_law/
│   ├── contracts
│   ├── property
│   ├── family
│   ├── inheritance
│   └── torts
├── criminal_law/
│   ├── felonies
│   ├── misdemeanors
│   ├── procedure
│   └── penalties
├── administrative_law/
├── constitutional_law/
├── labor_law/
└── commercial_law/
```

#### 2. Procedure Tags
```
procedure/
├── litigation/
│   ├── civil_procedure
│   ├── criminal_procedure
│   └── administrative_procedure
└── alternative_dispute_resolution/
    ├── mediation
    ├── arbitration
    └── conciliation
```

#### 3. Jurisdiction Tags
```
jurisdiction/
├── croatia/
│   ├── national
│   ├── regional
│   └── local
├── european_union/
└── international/
```

#### 4. Document Type Tags
```
document_type/
├── primary_legislation/
├── secondary_legislation/
└── case_law/
```

#### 5. Topic Tags
```
topic/
├── human_rights/
├── economic_rights/
├── environmental/
└── digital/
```

### Auto-Tagging

Documents are automatically tagged based on:
- Content analysis (keyword patterns)
- Metadata (jurisdiction, court, date)
- Legal domain patterns (Croatian legal terms)

### Manual Tagging

```php
use App\Services\TaggingService;

$tagging = app(TaggingService::class);

// Apply tag
$tagging->applyTag('LawDocument', $lawId, 'civil_law');

// Get tags
$tags = $tagging->getNodeTags('LawDocument', $lawId);

// Get related tags
$related = $tagging->getRelatedTags('civil_law');
```

## Usage

### Automatic Sync on Ingestion

When new documents are ingested through `LawVectorStoreService` or `CaseVectorStoreService`, they're automatically synced to the graph database if `NEO4J_AUTO_SYNC=true`.

### Manual Sync

```php
use App\Services\GraphRagService;

$graphRag = app(GraphRagService::class);

// Sync single document
$graphRag->syncLaw($lawId);
$graphRag->syncCase($caseDocId);

// Batch sync
$result = $graphRag->syncAllLaws();
// Returns: ['synced' => 150, 'errors' => 0]
```

### Enhanced RAG Queries

```php
use App\Services\GraphRagService;

$graphRag = app(GraphRagService::class);

// Query with graph context
$results = $graphRag->enhancedQuery(
    query: 'contract disputes',
    contextType: 'both', // 'law', 'case', or 'both'
    limit: 10
);

// Returns:
// [
//     'direct_matches' => [...],
//     'related_via_tags' => [...],
//     'related_via_keywords' => [...],
//     'similar_documents' => [...],
//     'graph_context' => [...]
// ]
```

### Get Graph Context

```php
// Get full context for a document
$context = $graphRag->getGraphContext('LawDocument', $lawId);

// Returns:
// [
//     'node' => [...document properties...],
//     'tags' => [...hierarchical tags...],
//     'keywords' => [...weighted keywords...],
//     'similar' => [...similar documents...],
//     'related' => [...related via shared tags...]
// ]
```

### Direct Graph Queries

```php
use App\Services\GraphDatabaseService;

$graph = app(GraphDatabaseService::class);

// Find related documents
$related = $graph->getRelated(
    label: 'LawDocument',
    id: $lawId,
    relType: 'SIMILAR_TO',
    depth: 2,
    limit: 20
);

// Find path between documents
$path = $graph->findPath(
    fromLabel: 'LawDocument',
    fromId: $lawId1,
    toLabel: 'CaseDocument',
    toId: $caseId,
    maxDepth: 5
);
```

## CLI Commands

### Initialize Database
```bash
php artisan graph:init
```

### Sync Data
```bash
# Sync everything
php artisan graph:sync --all

# Sync only laws
php artisan graph:sync --laws

# Sync only cases
php artisan graph:sync --cases
```

### Query Graph
```bash
php artisan graph:query "ugovorno pravo" --type=law --limit=10
```

### View Statistics
```bash
php artisan graph:stats
```

Output:
```
Graph Database Statistics

Node Counts:
  LawDocument: 1250
  CaseDocument: 832
  Keyword: 3421
  Tag: 156
  Jurisdiction: 12
  Court: 45

Relationship Counts:
  HAS_TAG: 5234
  HAS_KEYWORD: 15678
  SIMILAR_TO: 2341
  BELONGS_TO_JURISDICTION: 2082

Top Tags:
  civil_law: 456 documents
  criminal_law: 321 documents
  ...
```

## Performance Optimization

### Indexes
The system automatically creates indexes on:
- Node IDs (unique constraints)
- Document titles
- Law numbers
- Dates
- Tag categories

### Batch Operations
Use batch operations for better performance:

```php
// Batch upsert nodes
$graph->batchUpsertNodes('Keyword', [
    ['id' => 'kw1', 'name' => 'contract'],
    ['id' => 'kw2', 'name' => 'property'],
]);
```

### Similarity Thresholds
Configure in `config/neo4j.php`:

```php
'similarity' => [
    'threshold' => 0.85,        // Only create relationships above this
    'min_threshold' => 0.70,    // Minimum to consider
    'max_relationships' => 10,  // Max similar docs per document
],
```

## Integration with Existing RAG

The graph database enhances the existing vector-based RAG system by:

1. **Relationship Discovery** - Find related documents through shared tags, keywords, citations
2. **Context Enrichment** - Add graph context to vector search results
3. **Multi-hop Reasoning** - Traverse relationships to find indirect connections
4. **Hierarchical Navigation** - Navigate tag hierarchies for broader/narrower concepts
5. **Similarity Networks** - Build similarity networks based on embeddings

### Example: Enhanced RAG Workflow

```php
// 1. Vector search (existing)
$vectorResults = $lawVectorStore->search($query, $limit);

// 2. Get graph context for top results
foreach ($vectorResults as $result) {
    $context = $graphRag->getGraphContext('LawDocument', $result['id']);
    
    // Add similar documents
    $result['similar'] = $context['similar'];
    
    // Add related via tags
    $result['related'] = $context['related'];
    
    // Add tag context
    $result['tags'] = $context['tags'];
}

// 3. Expand results with graph traversal
$expanded = $graphRag->enhancedQuery($query, 'law', 20);

// 4. Merge and rank results
$finalResults = mergeAndRank($vectorResults, $expanded);
```

## Monitoring

### Check Connection
```php
use App\Services\GraphDatabaseService;

$graph = app(GraphDatabaseService::class);
$result = $graph->run("RETURN 'connected' as status");
```

### View Logs
Graph operations are logged to `storage/logs/laravel.log` with context:
- Query executed
- Parameters
- Errors
- Sync operations

## Troubleshooting

### Connection Issues
```bash
# Test Neo4j connection
docker exec -it neo4j cypher-shell -u neo4j -p your_password

# Check if Neo4j is running
docker ps | grep neo4j
```

### Sync Failures
- Check `storage/logs/laravel.log` for details
- Verify embeddings exist in relational DB
- Ensure Neo4j has sufficient memory
- Check constraint violations

### Performance Issues
- Increase Neo4j memory: `-e NEO4J_dbms_memory_heap_max__size=4G`
- Optimize indexes
- Reduce similarity threshold
- Limit max relationships per node

## Future Enhancements

- [ ] Citation extraction and linking
- [ ] Temporal relationships (amendments, supersedes)
- [ ] Entity extraction (persons, organizations)
- [ ] Advanced NLP for keyword extraction
- [ ] Graph embeddings (Node2Vec)
- [ ] Community detection for topic clustering
- [ ] Graph neural networks for recommendations

## License

Same as parent project.

