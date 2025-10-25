<?php

namespace App\Agents;

use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\AgentEvaluationService;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Agents\BaseLlmAgent;

/**
 * Autonomous agent for self-study on legal topics.
 * Implements a plan→act→evaluate loop with budget and time constraints.
 */
class AutonomousResearchAgent extends BaseLlmAgent
{
    protected string $name = 'autonomous_research_agent';

    protected string $description = 'Autonomous agent for legal research and self-study. Searches laws, cases, decisions, and saves insights.';

    protected ?string $provider = 'openai';
    protected string $model = 'gpt-4o-mini';

    protected int $maxSteps = 20;

    protected bool $showInChatUi = false;

    protected bool $includeConversationHistory = true;
    protected int $historyLimit = 10;
    protected string $contextStrategy = 'recent';

    protected bool $useStatefulResponses = true;

    protected ?AgentToolbox $toolbox = null;
    protected ?AgentEvaluationService $evaluator = null;
    protected ?AgentRun $currentRun = null;

    public function __construct()
    {
        parent::__construct();
        $this->toolbox = app(AgentToolbox::class);
        $this->evaluator = app(AgentEvaluationService::class);
        $this->instructions = $this->buildInstructions();
    }

    /**
     * Start a research run with objective and constraints
     *
     * @param string $objective The research objective/goal
     * @param array $context Additional context for the research
     * @param array $constraints Budget and time constraints:
     *   - token_budget: max tokens
     *   - cost_budget: max cost in USD
     *   - time_limit_seconds: max execution time
     *   - max_iterations: max research iterations
     * @return AgentRun The created run
     */
    public function startRun(string $objective, array $context = [], array $constraints = []): AgentRun
    {
        $run = AgentRun::create([
            'agent_name' => $this->name,
            'objective' => $objective,
            'context' => $context,
            'topics' => $context['topics'] ?? [],
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => $constraints['max_iterations'] ?? 10,
            'threshold' => $constraints['threshold'] ?? 0.75,
            'token_budget' => $constraints['token_budget'] ?? null,
            'tokens_used' => 0,
            'cost_budget' => $constraints['cost_budget'] ?? null,
            'cost_spent' => 0,
            'time_limit_seconds' => $constraints['time_limit_seconds'] ?? null,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $this->currentRun = $run;

        Log::info('Started autonomous research run', [
            'run_id' => $run->id,
            'objective' => $objective,
            'constraints' => $constraints,
        ]);

        return $run;
    }

    /**
     * Execute the autonomous research loop
     *
     * @param AgentRun $run The run to execute
     * @return AgentRun The completed/updated run
     */
    public function executeRun(AgentRun $run): AgentRun
    {
        $this->currentRun = $run;
        $iterations = $run->iterations ?? [];

        try {
            while ($this->shouldContinue($run)) {
                $iteration = $this->executeIteration($run);
                $iterations[] = $iteration;

                $run->update([
                    'current_iteration' => $run->current_iteration + 1,
                    'iterations' => $iterations,
                    'tokens_used' => $iteration['tokens_used'] ?? $run->tokens_used,
                    'cost_spent' => $iteration['cost_spent'] ?? $run->cost_spent,
                ]);

                // Check if we've reached a good stopping point
                if (isset($iteration['evaluation']) && $iteration['evaluation']['should_stop']) {
                    break;
                }
            }

            // Generate final output and evaluate
            $finalOutput = $this->synthesizeFinalOutput($run);
            $finalEvaluation = $this->evaluator->evaluateRun($run->id, $finalOutput);

            $run->update([
                'status' => 'completed',
                'final_output' => $finalOutput,
                'score' => $finalEvaluation['score'] ?? 0,
                'completed_at' => now(),
                'elapsed_seconds' => now()->diffInSeconds($run->started_at),
            ]);

            Log::info('Completed autonomous research run', [
                'run_id' => $run->id,
                'iterations' => count($iterations),
                'score' => $run->score,
            ]);
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
                'elapsed_seconds' => now()->diffInSeconds($run->started_at),
            ]);

            Log::error('Autonomous research run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $run->fresh();
    }

    /**
     * Check if the agent should continue researching
     */
    protected function shouldContinue(AgentRun $run): bool
    {
        // Check iteration limit
        if ($run->current_iteration >= $run->max_iterations) {
            return false;
        }

        // Check time limit
        if ($run->time_limit_seconds && $run->started_at) {
            $elapsed = now()->diffInSeconds($run->started_at);
            if ($elapsed >= $run->time_limit_seconds) {
                return false;
            }
        }

        // Check token budget
        if ($run->token_budget && $run->tokens_used >= $run->token_budget) {
            return false;
        }

        // Check cost budget
        if ($run->cost_budget && $run->cost_spent >= $run->cost_budget) {
            return false;
        }

        return true;
    }

    /**
     * Execute a single research iteration
     */
    protected function executeIteration(AgentRun $run): array
    {
        $iteration = [
            'number' => $run->current_iteration + 1,
            'started_at' => now()->toIso8601String(),
            'actions' => [],
        ];

        try {
            // Plan: What should we research next?
            $plan = $this->planNextStep($run);
            $iteration['plan'] = $plan;

            // Act: Execute the planned actions
            $actionResults = $this->executeActions($plan['actions'] ?? [], $run);
            $iteration['actions'] = $actionResults;

            // Evaluate: Assess what we learned
            $evaluation = $this->evaluateIteration($run, $iteration);
            $iteration['evaluation'] = $evaluation;

            // Save insights to memory
            if (!empty($evaluation['insights'])) {
                $this->saveInsights($evaluation['insights'], $run);
            }

            $iteration['completed_at'] = now()->toIso8601String();
        } catch (\Exception $e) {
            $iteration['error'] = $e->getMessage();
            Log::error('Iteration failed', [
                'run_id' => $run->id,
                'iteration' => $iteration['number'],
                'error' => $e->getMessage(),
            ]);
        }

        return $iteration;
    }

    /**
     * Plan the next research step
     */
    protected function planNextStep(AgentRun $run): array
    {
        $previousIterations = $run->iterations ?? [];
        $context = $this->buildPlanningContext($run, $previousIterations);

        $prompt = "Based on the objective and previous research, what should we investigate next?\n\n{$context}\n\nProvide a structured plan with 1-3 specific actions to take. Each action should specify the tool to use and the parameters.";

        // Use the LLM to plan (this would integrate with the LLM provider)
        $plan = [
            'reasoning' => 'Determining next research steps based on objective and previous findings',
            'actions' => $this->generateActions($run),
        ];

        return $plan;
    }

    /**
     * Generate research actions based on objective
     */
    protected function generateActions(AgentRun $run): array
    {
        // For the initial iterations, generate exploratory actions
        $actions = [];

        if ($run->current_iteration === 0) {
            // First iteration: broad vector search
            $actions[] = [
                'tool' => 'vector_search',
                'params' => [
                    'query' => $run->objective,
                    'types' => ['laws', 'cases', 'decisions'],
                    'limit' => 5,
                ],
            ];
        } else {
            // Subsequent iterations: more targeted research based on context
            $topics = $run->topics ?? [];
            if (!empty($topics)) {
                $actions[] = [
                    'tool' => 'vector_search',
                    'params' => [
                        'query' => implode(' ', array_slice($topics, 0, 3)),
                        'types' => ['laws', 'decisions'],
                        'limit' => 3,
                    ],
                ];
            }

            // Check for related laws in graph
            $actions[] = [
                'tool' => 'graph_query',
                'params' => [
                    'cypher' => 'MATCH (l:LawDocument)-[:CITES]->(cited:LawDocument) WHERE l.title CONTAINS $topic RETURN cited.title, cited.law_number LIMIT 5',
                    'parameters' => ['topic' => $topics[0] ?? 'law'],
                ],
            ];
        }

        return $actions;
    }

    /**
     * Execute planned actions using the toolbox
     */
    protected function executeActions(array $actions, AgentRun $run): array
    {
        $results = [];

        foreach ($actions as $action) {
            $tool = $action['tool'] ?? null;
            $params = $action['params'] ?? [];

            if (!$tool) {
                continue;
            }

            try {
                $result = match ($tool) {
                    'vector_search' => $this->toolbox->vectorSearch($params['query'], $params),
                    'law_lookup' => $this->toolbox->lawLookup($params['law_number'], $params['jurisdiction'] ?? null),
                    'decision_lookup' => $this->toolbox->decisionLookup($params),
                    'graph_query' => $this->toolbox->graphQuery($params['cypher'], $params['parameters'] ?? []),
                    'web_fetch' => $this->toolbox->webFetch($params['url'], $params),
                    default => ['error' => "Unknown tool: {$tool}"],
                };

                $results[] = [
                    'tool' => $tool,
                    'params' => $params,
                    'result' => $result,
                    'success' => !isset($result['error']),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'tool' => $tool,
                    'params' => $params,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Evaluate what was learned in this iteration
     */
    protected function evaluateIteration(AgentRun $run, array $iteration): array
    {
        $actionResults = $iteration['actions'] ?? [];
        $insights = [];
        $shouldStop = false;

        // Extract insights from action results
        foreach ($actionResults as $actionResult) {
            if ($actionResult['success'] ?? false) {
                $insight = $this->extractInsight($actionResult['result'], $run->objective);
                if ($insight) {
                    $insights[] = $insight;
                }
            }
        }

        // Determine if we should stop (e.g., if we found sufficient information)
        if (count($insights) >= 3 && $run->current_iteration >= 3) {
            $shouldStop = true;
        }

        return [
            'insights' => $insights,
            'insights_count' => count($insights),
            'should_stop' => $shouldStop,
        ];
    }

    /**
     * Extract insight from action result
     */
    protected function extractInsight(array $result, string $objective): ?string
    {
        // Simple extraction - in real implementation, this would use LLM to summarize
        if (isset($result['laws']) && is_array($result['laws']) && count($result['laws']) > 0) {
            $law = $result['laws'][0];
            return "Found relevant law: {$law['title']} ({$law['law_number']})";
        }

        if (isset($result['decisions']) && is_array($result['decisions']) && count($result['decisions']) > 0) {
            $decision = $result['decisions'][0];
            return "Found relevant decision: {$decision['title']} from {$decision['court']}";
        }

        if (isset($result['rows']) && is_array($result['rows']) && count($result['rows']) > 0) {
            return "Found " . count($result['rows']) . " related entities in graph";
        }

        return null;
    }

    /**
     * Save insights to agent vector memory
     */
    protected function saveInsights(array $insights, AgentRun $run): void
    {
        foreach ($insights as $insight) {
            if (is_string($insight) && !empty($insight)) {
                $this->toolbox->noteSave($this->name, $insight, [
                    'namespace' => 'research_insights',
                    'metadata' => [
                        'run_id' => $run->id,
                        'objective' => $run->objective,
                        'iteration' => $run->current_iteration,
                    ],
                    'source' => 'autonomous_research',
                    'source_id' => (string)$run->id,
                ]);
            }
        }
    }

    /**
     * Build context for planning
     */
    protected function buildPlanningContext(AgentRun $run, array $previousIterations): string
    {
        $context = "Objective: {$run->objective}\n\n";

        if (!empty($run->topics)) {
            $context .= "Topics: " . implode(', ', $run->topics) . "\n\n";
        }

        if (!empty($previousIterations)) {
            $context .= "Previous findings:\n";
            foreach ($previousIterations as $iter) {
                if (isset($iter['evaluation']['insights'])) {
                    foreach ($iter['evaluation']['insights'] as $insight) {
                        $context .= "- {$insight}\n";
                    }
                }
            }
        }

        return $context;
    }

    /**
     * Synthesize final output from all iterations
     */
    protected function synthesizeFinalOutput(AgentRun $run): string
    {
        $output = "# Research Report: {$run->objective}\n\n";
        $output .= "## Summary\n\n";
        $output .= "Completed {$run->current_iteration} research iterations.\n\n";

        $output .= "## Key Findings\n\n";

        $allInsights = [];
        foreach ($run->iterations as $iteration) {
            if (isset($iteration['evaluation']['insights'])) {
                $allInsights = array_merge($allInsights, $iteration['evaluation']['insights']);
            }
        }

        if (empty($allInsights)) {
            $output .= "No significant findings were discovered during this research.\n";
        } else {
            foreach (array_unique($allInsights) as $i => $insight) {
                $output .= ($i + 1) . ". {$insight}\n";
            }
        }

        $output .= "\n## Research Process\n\n";
        $output .= "- Total iterations: {$run->current_iteration}\n";
        $output .= "- Elapsed time: {$run->elapsed_seconds} seconds\n";
        if ($run->tokens_used) {
            $output .= "- Tokens used: {$run->tokens_used}\n";
        }
        if ($run->cost_spent) {
            $output .= "- Cost: \${$run->cost_spent}\n";
        }

        return $output;
    }

    private function buildInstructions(): string
    {
        $today = date('Y-m-d');
        return <<<SYS
You are an autonomous legal research assistant specialized in Croatian law.

Your mission is to conduct thorough legal research on given objectives by:
1. Searching vector databases for relevant laws, cases, and court decisions
2. Querying the knowledge graph for legal citations and relationships
3. Extracting insights and patterns from discovered documents
4. Saving important findings to long-term memory
5. Synthesizing findings into comprehensive reports

Operating principles:
1. Start broad, then narrow focus based on findings
2. Always cite specific laws, cases, or decisions
3. Look for patterns and connections in the legal landscape
4. Validate findings by cross-referencing multiple sources
5. Save key insights for future reference
6. Work within budget and time constraints
7. Self-evaluate progress and adjust strategy

Available tools via AgentToolbox:
- vector_search: Semantic search across laws, cases, decisions
- law_lookup: Find specific laws by number/jurisdiction
- decision_lookup: Find court decisions by criteria
- graph_query: Query Neo4j for relationships and citations
- web_fetch: Fetch external web content
- note_save: Save insights to long-term memory

Language: Provide analysis in Croatian for Croatian legal content, English otherwise.
Current date: {$today}
SYS;
    }
}
