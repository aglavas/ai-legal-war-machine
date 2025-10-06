<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LawUpload extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'doc_id', 'ingested_law_id', 'disk', 'local_path', 'original_filename', 'mime_type', 'file_size',
        'sha256', 'source_url', 'downloaded_at', 'status', 'error',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.law_uploads', 'law_uploads');
    }

    public function ingestedLaw()
    {
        return $this->belongsTo(IngestedLaw::class, 'ingested_law_id');
    }
}
