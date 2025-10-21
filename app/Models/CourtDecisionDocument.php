<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtDecisionDocument extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'decision_id', 'doc_id', 'upload_id', 'title', 'category', 'author', 'language', 'tags',
        'chunk_index', 'content', 'metadata', 'source', 'source_id', 'embedding_provider',
        'embedding_model', 'embedding_dimensions', 'embedding_norm', 'content_hash', 'token_count',
        'embedding_vector', 'embedding',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'embedding_vector' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.court_decision_documents', 'court_decision_documents');
    }

    public function decision()
    {
        return $this->belongsTo(CourtDecision::class, 'decision_id');
    }

    public function upload()
    {
        return $this->belongsTo(CourtDecisionDocumentUpload::class, 'upload_id');
    }
}
