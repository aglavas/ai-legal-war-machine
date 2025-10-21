<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkomPredmet extends Model
{
    protected $table = 'ekom_predmeti';

    protected $fillable = [
        'remote_id',
        'oznaka',
        'status',
        'sud_remote_id',
        'do_not_disturb',
        'data',
        'last_synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'do_not_disturb' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
