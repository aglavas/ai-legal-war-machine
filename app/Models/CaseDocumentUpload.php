<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CaseDocumentUpload extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_id', 'doc_id', 'disk', 'local_path', 'original_filename', 'mime_type', 'file_size',
        'sha256', 'source_url', 'uploaded_at', 'status', 'error',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');
    }

    public function case()
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }
}
