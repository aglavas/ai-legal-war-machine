<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            // Budget tracking
            $table->decimal('token_budget', 12, 2)->nullable()->after('threshold')
                ->comment('Maximum tokens allowed for this run');
            $table->decimal('tokens_used', 12, 2)->default(0)->after('token_budget')
                ->comment('Tokens consumed so far');
            $table->decimal('cost_budget', 10, 4)->nullable()->after('tokens_used')
                ->comment('Maximum cost in USD allowed for this run');
            $table->decimal('cost_spent', 10, 4)->default(0)->after('cost_budget')
                ->comment('Cost spent so far in USD');

            // Time tracking
            $table->integer('time_limit_seconds')->nullable()->after('cost_spent')
                ->comment('Maximum execution time in seconds');
            $table->timestamp('started_at')->nullable()->after('time_limit_seconds')
                ->comment('When the run started');
            $table->timestamp('completed_at')->nullable()->after('started_at')
                ->comment('When the run completed');
            $table->integer('elapsed_seconds')->nullable()->after('completed_at')
                ->comment('Total execution time in seconds');

            // Agent identification
            $table->string('agent_name')->nullable()->after('id')
                ->comment('Name of the agent running this');

            // Topics for scheduled re-runs
            $table->json('topics')->nullable()->after('context')
                ->comment('Topics being studied for scheduled re-runs');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn([
                'token_budget',
                'tokens_used',
                'cost_budget',
                'cost_spent',
                'time_limit_seconds',
                'started_at',
                'completed_at',
                'elapsed_seconds',
                'agent_name',
                'topics',
            ]);
        });
    }
};
