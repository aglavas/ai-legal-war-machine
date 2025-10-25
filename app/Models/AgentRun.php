<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentRun extends Model
{
    protected $fillable = [
        'agent_name',
        'objective',
        'context',
        'topics',
        'iterations',
        'status',
        'current_iteration',
        'score',
        'max_iterations',
        'threshold',
        'token_budget',
        'tokens_used',
        'cost_budget',
        'cost_spent',
        'time_limit_seconds',
        'started_at',
        'completed_at',
        'elapsed_seconds',
        'final_output',
        'error',
    ];

    protected $casts = [
        'context' => 'array',
        'topics' => 'array',
        'iterations' => 'array',
        'score' => 'float',
        'threshold' => 'float',
        'token_budget' => 'decimal:2',
        'tokens_used' => 'decimal:2',
        'cost_budget' => 'decimal:4',
        'cost_spent' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
