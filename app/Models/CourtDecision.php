<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtDecision extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_number', 'title', 'court', 'jurisdiction',
        'judge', 'decision_date', 'publication_date', 'decision_type',
        'register', 'finality', 'ecli', 'tags', 'description',
    ];

    protected $casts = [
        'tags' => 'array',
        'decision_date' => 'date',
        'publication_date' => 'date',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.court_decisions', 'court_decisions');
    }

    // Relations
    public function documents()
    {
        return $this->hasMany(CourtDecisionDocument::class, 'decision_id');
    }

    public function uploads()
    {
        return $this->hasMany(CourtDecisionDocumentUpload::class, 'decision_id');
    }
}

