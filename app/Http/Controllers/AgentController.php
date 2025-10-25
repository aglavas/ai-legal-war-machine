<?php

namespace App\Http\Controllers;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for autonomous agent operations
 */
class AgentController extends Controller
{
    public function __construct(
        protected AutonomousResearchAgent $agent,
        protected AgentEvaluationService $evaluator
    ) {
    }

    /**
     * Start a new autonomous research run
     *
     * POST /api/agent/research/start
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startResearch(Request $request)
    {
        $data = $request->validate([
            'objective' => ['required', 'string', 'min:10'],
            'topics' => ['sometimes', 'array'],
            'topics.*' => ['string'],
            'jurisdiction' => ['sometimes', 'string'],
            'max_iterations' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'token_budget' => ['sometimes', 'numeric', 'min:0'],
            'cost_budget' => ['sometimes', 'numeric', 'min:0'],
            'time_limit_seconds' => ['sometimes', 'integer', 'min:10', 'max:7200'],
        ]);

        $context = [
            'topics' => $data['topics'] ?? [],
            'jurisdiction' => $data['jurisdiction'] ?? 'Croatia',
            'requested_by' => $request->user()?->id ?? 'system',
            'ip' => $request->ip(),
        ];

        $constraints = [
            'max_iterations' => $data['max_iterations'] ?? 10,
            'threshold' => $data['threshold'] ?? 0.75,
            'token_budget' => $data['token_budget'] ?? null,
            'cost_budget' => $data['cost_budget'] ?? null,
            'time_limit_seconds' => $data['time_limit_seconds'] ?? null,
        ];

        try {
            $run = $this->agent->startRun($data['objective'], $context, $constraints);

            // Execute asynchronously (in production, this should be queued)
            // For now, execute synchronously
            $run = $this->agent->executeRun($run);

            return response()->json([
                'success' => true,
                'run' => [
                    'id' => $run->id,
                    'objective' => $run->objective,
                    'status' => $run->status,
                    'score' => $run->score,
                    'iterations' => $run->current_iteration,
                    'elapsed_seconds' => $run->elapsed_seconds,
                    'final_output' => $run->final_output,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start research run', [
                'objective' => $data['objective'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get status of a research run
     *
     * GET /api/agent/research/{id}
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResearch(int $id)
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => 'Run not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'run' => [
                'id' => $run->id,
                'agent_name' => $run->agent_name,
                'objective' => $run->objective,
                'topics' => $run->topics,
                'status' => $run->status,
                'score' => $run->score,
                'threshold' => $run->threshold,
                'current_iteration' => $run->current_iteration,
                'max_iterations' => $run->max_iterations,
                'tokens_used' => $run->tokens_used,
                'cost_spent' => $run->cost_spent,
                'elapsed_seconds' => $run->elapsed_seconds,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'final_output' => $run->final_output,
                'error' => $run->error,
                'iterations' => $run->iterations,
            ],
        ]);
    }

    /**
     * List all research runs
     *
     * GET /api/agent/research
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listResearch(Request $request)
    {
        $query = AgentRun::query()->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('agent_name')) {
            $query->where('agent_name', $request->input('agent_name'));
        }

        $limit = min((int)$request->input('limit', 20), 100);
        $runs = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'runs' => $runs->map(fn($run) => [
                'id' => $run->id,
                'agent_name' => $run->agent_name,
                'objective' => $run->objective,
                'status' => $run->status,
                'score' => $run->score,
                'iterations' => $run->current_iteration,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'elapsed_seconds' => $run->elapsed_seconds,
            ]),
            'count' => $runs->count(),
        ]);
    }

    /**
     * Get evaluation report for a run
     *
     * GET /api/agent/research/{id}/evaluation
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEvaluation(int $id)
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => 'Run not found',
            ], 404);
        }

        if ($run->status !== 'completed') {
            return response()->json([
                'success' => false,
                'error' => 'Run is not completed yet',
            ], 400);
        }

        try {
            $report = $this->evaluator->generateReport($id);

            return response()->json([
                'success' => true,
                'run_id' => $id,
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate evaluation report', [
                'run_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a research run
     *
     * DELETE /api/agent/research/{id}
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteResearch(int $id)
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => 'Run not found',
            ], 404);
        }

        try {
            $run->delete();

            return response()->json([
                'success' => true,
                'message' => 'Run deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete run', [
                'run_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View dashboard (web route)
     *
     * GET /agent/dashboard
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function dashboard()
    {
        $runs = AgentRun::orderBy('created_at', 'desc')->limit(50)->get();

        $stats = [
            'total' => AgentRun::count(),
            'running' => AgentRun::where('status', 'running')->count(),
            'completed' => AgentRun::where('status', 'completed')->count(),
            'failed' => AgentRun::where('status', 'failed')->count(),
            'avg_score' => AgentRun::where('status', 'completed')->avg('score'),
            'avg_iterations' => AgentRun::where('status', 'completed')->avg('current_iteration'),
            'avg_duration' => AgentRun::where('status', 'completed')->avg('elapsed_seconds'),
        ];

        return view('agent.dashboard', compact('runs', 'stats'));
    }

    /**
     * View individual run details (web route)
     *
     * GET /agent/run/{id}
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function viewRun(int $id)
    {
        $run = AgentRun::findOrFail($id);
        $evaluation = null;

        if ($run->status === 'completed' && $run->final_output) {
            $evaluation = $this->evaluator->evaluateRun($id, $run->final_output);
        }

        return view('agent.run', compact('run', 'evaluation'));
    }
}
