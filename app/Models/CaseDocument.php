<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseDocument extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_id', 'doc_id', 'upload_id', 'title', 'category', 'author', 'language', 'tags',
        'chunk_index', 'content', 'metadata', 'actual', 'source', 'source_id', 'embedding_provider',
        'embedding_model', 'embedding_dimensions', 'embedding_norm', 'content_hash', 'token_count',
        'embedding_vector', 'embedding',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'actual' => 'array',
        'embedding_vector' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.cases_documents', 'cases_documents');
    }

    public function case()
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function upload()
    {
        return $this->belongsTo(CaseDocumentUpload::class, 'upload_id');
    }

    /**
     * Boot the model with event listeners for graph database synchronization
     */
    protected static function booted()
    {
        static::updated(function ($caseDocument) {
            if (config('neo4j.sync.auto_sync')) {
                app(\App\Services\GraphRagService::class)->syncCase($caseDocument->id);
            }
        });

        static::deleted(function ($caseDocument) {
            if (config('neo4j.sync.enabled')) {
                app(\App\Services\GraphDatabaseService::class)->deleteNode('CaseDocument', $caseDocument->id);
            }
        });
    }
}

