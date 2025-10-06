<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TextractJob extends Model
{
    protected $fillable = [
        'drive_file_id', 'drive_file_name', 's3_key', 'job_id', 'status', 'error'
    ];
}

