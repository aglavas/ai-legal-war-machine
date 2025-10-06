<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentVectorMemory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id',
        'agent_name',
        'namespace',
        'content',
        'metadata',
        'source',
        'source_id',
        'chunk_index',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'embedding_norm',
        'content_hash',
        'token_count',
        'embedding_vector', // for non-pgsql
        'embedding',         // for pgsql pgvector column
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding_vector' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
    }
}
