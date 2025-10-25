<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job for executing autonomous agent research runs
 */
class ExecuteAgentResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    /**
     * The agent run to execute
     */
    protected AgentRun $run;

    /**
     * Create a new job instance.
     */
    public function __construct(AgentRun $run)
    {
        $this->run = $run;

        // Set timeout based on run's time limit, with safety buffer
        $this->timeout = $run->time_limit_seconds
            ? $run->time_limit_seconds + 60
            : config('agent.safety.max_time_per_run', 3600) + 60;

        // Set queue if specified
        if ($queue = config('agent.queue.name')) {
            $this->onQueue($queue);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(AutonomousResearchAgent $agent): void
    {
        Log::info('Starting async agent research', [
            'job_id' => $this->job?->getJobId(),
            'run_id' => $this->run->id,
            'objective' => $this->run->objective,
        ]);

        try {
            // Check if run is still in running status
            $this->run->refresh();

            if ($this->run->status !== 'running') {
                Log::warning('Agent run is not in running status, skipping', [
                    'run_id' => $this->run->id,
                    'status' => $this->run->status,
                ]);
                return;
            }

            // Execute the research
            $agent->executeRun($this->run);

            Log::info('Async agent research completed', [
                'run_id' => $this->run->id,
                'status' => $this->run->fresh()->status,
                'score' => $this->run->fresh()->score,
            ]);
        } catch (\Exception $e) {
            Log::error('Async agent research failed', [
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update run with error
            $this->run->update([
                'status' => 'failed',
                'error' => 'Job execution failed: ' . $e->getMessage(),
                'completed_at' => now(),
                'elapsed_seconds' => $this->run->started_at
                    ? now()->diffInSeconds($this->run->started_at)
                    : 0,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Agent research job failed permanently', [
            'run_id' => $this->run->id,
            'error' => $exception->getMessage(),
        ]);

        // Update run status
        $this->run->update([
            'status' => 'failed',
            'error' => 'Job failed: ' . $exception->getMessage(),
            'completed_at' => now(),
            'elapsed_seconds' => $this->run->started_at
                ? now()->diffInSeconds($this->run->started_at)
                : 0,
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'agent:research',
            'run:' . $this->run->id,
            'agent:' . ($this->run->agent_name ?? 'unknown'),
        ];
    }
}
