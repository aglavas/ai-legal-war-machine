<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Neo4j Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Neo4j graph database connection used for RAG system
    | to store relationships between laws, cases, keywords, and topics.
    |
    */

    'default' => env('NEO4J_CONNECTION', 'bolt'),
    'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),
    'user' => env('NEO4J_USER', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'secret'),
    'alias' => 'neo4j',

    'connections' => [
        'bolt' => [
            'driver' => 'bolt',
            'host' => env('NEO4J_HOST', 'localhost'),
            'port' => env('NEO4J_PORT', 7687),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'neo4j'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ],

        'http' => [
            'driver' => 'http',
            'host' => env('NEO4J_HOST', 'localhost'),
            'port' => env('NEO4J_HTTP_PORT', 7474),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', 'neo4j'),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Schema Configuration
    |--------------------------------------------------------------------------
    |
    | Define node labels and relationship types used in the graph database.
    |
    */

    'nodes' => [
        'law' => 'Law',
        'law_document' => 'LawDocument',
        'case' => 'Case',
        'case_document' => 'CaseDocument',
        'keyword' => 'Keyword',
        'topic' => 'Topic',
        'tag' => 'Tag',
        'jurisdiction' => 'Jurisdiction',
        'court' => 'Court',
        'legal_concept' => 'LegalConcept',
    ],

    'relationships' => [
        'cites' => 'CITES',
        'references' => 'REFERENCES',
        'relates_to' => 'RELATES_TO',
        'has_keyword' => 'HAS_KEYWORD',
        'has_tag' => 'HAS_TAG',
        'belongs_to_jurisdiction' => 'BELONGS_TO_JURISDICTION',
        'decided_by' => 'DECIDED_BY',
        'supersedes' => 'SUPERSEDES',
        'amended_by' => 'AMENDED_BY',
        'similar_to' => 'SIMILAR_TO',
        'contains_concept' => 'CONTAINS_CONCEPT',
        'parent_tag' => 'PARENT_TAG',
        'contradicts' => 'CONTRADICTS',
        'supports' => 'SUPPORTS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Similarity Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure similarity thresholds for creating graph relationships
    | based on vector embeddings.
    |
    */

    'similarity' => [
        'threshold' => env('NEO4J_SIMILARITY_THRESHOLD', 0.85),
        'min_threshold' => 0.70,
        'max_relationships' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how data is synced between relational DB and graph DB.
    |
    */

    'sync' => [
        'batch_size' => env('NEO4J_SYNC_BATCH_SIZE', 100),
        'auto_sync' => env('NEO4J_AUTO_SYNC', true),
        'enabled' => env('NEO4J_ENABLED', true),
    ],
];

