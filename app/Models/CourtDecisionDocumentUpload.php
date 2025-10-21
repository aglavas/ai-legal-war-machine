<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtDecisionDocumentUpload extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'decision_id', 'doc_id', 'disk', 'local_path', 'original_filename', 'mime_type', 'file_size',
        'sha256', 'source_url', 'uploaded_at', 'status', 'error',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.court_decision_document_uploads', 'court_decision_document_uploads');
    }

    public function decision()
    {
        return $this->belongsTo(CourtDecision::class, 'decision_id');
    }
}

