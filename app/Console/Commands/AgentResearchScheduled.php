<?php

namespace App\Console\Commands;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AgentResearchScheduled extends Command
{
    protected $signature = 'agent:research-scheduled
                            {--topics=* : Specific topics to research}
                            {--max-iterations=10 : Maximum research iterations}
                            {--time-limit=600 : Time limit in seconds}
                            {--token-budget= : Token budget}
                            {--cost-budget= : Cost budget in USD}';

    protected $description = 'Run scheduled autonomous research on active topics';

    public function __construct(protected AutonomousResearchAgent $agent)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting scheduled autonomous research...');

        try {
            // Get topics from options or determine from recent runs
            $topics = $this->option('topics');

            if (empty($topics)) {
                // Find active topics from recent successful runs
                $topics = $this->getActiveTopics();
            }

            if (empty($topics)) {
                $this->warn('No active topics found. Skipping research run.');
                return self::SUCCESS;
            }

            $this->info('Research topics: ' . implode(', ', $topics));

            // Generate objective from topics
            $objective = $this->generateObjective($topics);

            // Set up constraints
            $constraints = [
                'max_iterations' => (int)$this->option('max-iterations'),
                'time_limit_seconds' => (int)$this->option('time-limit'),
                'threshold' => 0.75,
            ];

            if ($this->option('token-budget')) {
                $constraints['token_budget'] = (float)$this->option('token-budget');
            }

            if ($this->option('cost-budget')) {
                $constraints['cost_budget'] = (float)$this->option('cost-budget');
            }

            // Start research run
            $run = $this->agent->startRun($objective, [
                'topics' => $topics,
                'jurisdiction' => 'Croatia',
                'source' => 'scheduled',
            ], $constraints);

            $this->info("Started research run #{$run->id}");

            // Execute the run
            $this->info('Executing research...');
            $completedRun = $this->agent->executeRun($run);

            // Report results
            $this->newLine();
            $this->info('Research completed!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Run ID', $completedRun->id],
                    ['Status', $completedRun->status],
                    ['Iterations', $completedRun->current_iteration],
                    ['Score', round($completedRun->score, 3)],
                    ['Elapsed Time', $completedRun->elapsed_seconds . ' seconds'],
                    ['Tokens Used', $completedRun->tokens_used ?? 'N/A'],
                    ['Cost', $completedRun->cost_spent ? '$' . $completedRun->cost_spent : 'N/A'],
                ]
            );

            if ($completedRun->status === 'completed') {
                $this->info('Final output preview:');
                $this->line(substr($completedRun->final_output, 0, 500) . '...');
            } elseif ($completedRun->status === 'failed') {
                $this->error('Run failed: ' . $completedRun->error);
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('agent:research-scheduled failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Research failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Get active topics from recent successful runs
     */
    protected function getActiveTopics(): array
    {
        // Find topics from the last 10 completed runs with good scores
        $runs = AgentRun::where('status', 'completed')
            ->where('score', '>=', 0.7)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $topicsMap = [];
        foreach ($runs as $run) {
            if (!empty($run->topics)) {
                foreach ($run->topics as $topic) {
                    $topicsMap[$topic] = ($topicsMap[$topic] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency and take top 3
        arsort($topicsMap);
        return array_slice(array_keys($topicsMap), 0, 3);
    }

    /**
     * Generate research objective from topics
     */
    protected function generateObjective(array $topics): string
    {
        if (count($topics) === 1) {
            return "Conduct comprehensive legal research on: {$topics[0]}";
        }

        return "Conduct comprehensive legal research on the following topics: " . implode(', ', $topics);
    }
}
