<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TextractJob extends Model
{
    protected $fillable = [
        'drive_file_id', 'drive_file_name', 'case_id', 's3_key', 'job_id', 'status', 'error', 'metadata'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
    ];
}
