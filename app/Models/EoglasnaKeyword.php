<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoglasnaKeyword extends Model
{
    protected $table = 'eoglasna_keywords';

    protected $fillable = [
        'query','scope','deep_scan','enabled','last_run_at','last_date_published','notes',
    ];

    protected $casts = [
        'deep_scan' => 'boolean',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'last_date_published' => 'datetime',
    ];

    public function matches(): HasMany
    {
        return $this->hasMany(EoglasnaKeywordMatch::class, 'keyword_id');
    }
}
