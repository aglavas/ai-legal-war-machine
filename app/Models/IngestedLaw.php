<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestedLaw extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'doc_id', 'title', 'law_number', 'jurisdiction', 'country', 'language',
        'source_url', 'aliases', 'keywords', 'keywords_text', 'metadata', 'ingested_at',
    ];

    protected $casts = [
        'aliases' => 'array',
        'keywords' => 'array',
        'metadata' => 'array',
        'ingested_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.ingested_laws', 'ingested_laws');
    }

    public function laws()
    {
        return $this->hasMany(Law::class, 'ingested_law_id');
    }

    public function uploads()
    {
        return $this->hasMany(LawUpload::class, 'ingested_law_id');
    }
}

