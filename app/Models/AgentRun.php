<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentRun extends Model
{
    protected $fillable = [
        'objective','context','iterations','status','current_iteration',
        'score','max_iterations','threshold','final_output','error',
    ];

    protected $casts = [
        'context' => 'array',
        'iterations' => 'array',
        'score' => 'float',
        'threshold' => 'float',
    ];
}
