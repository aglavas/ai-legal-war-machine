<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalCase extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_number', 'title', 'client_name', 'opponent_name', 'court', 'jurisdiction',
        'judge', 'filing_date', 'status', 'tags', 'description',
    ];

    protected $casts = [
        'tags' => 'array',
        'filing_date' => 'date',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.cases', 'cases');
    }

    // Relations
    public function documents()
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function uploads()
    {
        return $this->hasMany(CaseDocumentUpload::class, 'case_id');
    }
}

