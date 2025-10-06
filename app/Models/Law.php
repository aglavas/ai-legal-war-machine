<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Law extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'doc_id', 'ingested_law_id', 'title', 'law_number', 'jurisdiction', 'country', 'language',
        'promulgation_date', 'effective_date', 'repeal_date', 'version', 'chapter', 'section',
        'tags', 'source_url', 'chunk_index', 'content', 'metadata', 'embedding_provider',
        'embedding_model', 'embedding_dimensions', 'embedding_norm', 'content_hash', 'token_count',
        'embedding_vector', 'embedding',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'embedding_vector' => 'array',
        'promulgation_date' => 'date',
        'effective_date' => 'date',
        'repeal_date' => 'date',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.laws', 'laws');
    }

    public function ingestedLaw()
    {
        return $this->belongsTo(IngestedLaw::class, 'ingested_law_id');
    }
}
