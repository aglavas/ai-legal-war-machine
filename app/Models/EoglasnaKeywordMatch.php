<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EoglasnaKeywordMatch extends Model
{
    protected $table = 'eoglasna_keyword_matches';

    protected $fillable = [
        'keyword_id','notice_uuid','matched_at','matched_fields',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'matched_fields' => 'array',
    ];
}
