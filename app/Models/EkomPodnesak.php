<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkomPodnesak extends Model
{
    protected $table = 'ekom_podnesci';

    protected $fillable = [
        'remote_id',
        'status',
        'sud_remote_id',
        'vrsta_podneska_remote_id',
        'vrijeme_kreiranja',
        'vrijeme_slanja',
        'vrijeme_zaprimanja',
        'data',
        'last_synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
