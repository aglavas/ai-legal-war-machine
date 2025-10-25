<?php

namespace App\Services;

use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

/**
 * Service for evaluating agent runs with self-check prompts and citation requirements
 */
class AgentEvaluationService
{
    public function __construct(
        protected OpenAIService $openai
    ) {
    }

    /**
     * Evaluate a completed agent run
     *
     * @param int $runId The agent run ID
     * @param string $output The final output to evaluate
     * @return array Evaluation results with score, pass/fail, and feedback
     */
    public function evaluateRun(int $runId, string $output): array
    {
        $run = AgentRun::find($runId);

        if (!$run) {
            return [
                'error' => 'Agent run not found',
                'score' => 0,
                'passed' => false,
            ];
        }

        // Run multiple evaluation checks
        $checks = [
            'completeness' => $this->evaluateCompleteness($run, $output),
            'citations' => $this->evaluateCitations($output),
            'relevance' => $this->evaluateRelevance($run, $output),
            'quality' => $this->evaluateQuality($output),
            'evidence' => $this->evaluateEvidence($run, $output),
        ];

        // Calculate overall score (weighted average)
        $weights = [
            'completeness' => 0.25,
            'citations' => 0.25,
            'relevance' => 0.20,
            'quality' => 0.15,
            'evidence' => 0.15,
        ];

        $totalScore = 0;
        foreach ($checks as $category => $check) {
            $totalScore += ($check['score'] ?? 0) * $weights[$category];
        }

        $passed = $totalScore >= ($run->threshold ?? 0.75);

        $evaluation = [
            'run_id' => $runId,
            'score' => round($totalScore, 3),
            'passed' => $passed,
            'threshold' => $run->threshold,
            'checks' => $checks,
            'evaluated_at' => now()->toIso8601String(),
        ];

        Log::info('Evaluated agent run', [
            'run_id' => $runId,
            'score' => $evaluation['score'],
            'passed' => $passed,
        ]);

        return $evaluation;
    }

    /**
     * Check if the output addresses the objective completely
     */
    protected function evaluateCompleteness(AgentRun $run, string $output): array
    {
        $prompt = $this->buildEvaluationPrompt(
            'completeness',
            $run->objective,
            $output,
            'Does this research output fully address the stated objective? Consider whether all aspects of the objective are covered.'
        );

        $result = $this->getLLMEvaluation($prompt);

        return [
            'score' => $result['score'],
            'passed' => $result['score'] >= 0.7,
            'feedback' => $result['feedback'],
            'missing_aspects' => $result['missing_aspects'] ?? [],
        ];
    }

    /**
     * Check if proper citations are provided
     */
    protected function evaluateCitations(string $output): array
    {
        // Pattern matching for common Croatian legal citations
        $patterns = [
            '/NN\s+\d+\/\d+/i', // Narodne Novine (e.g., NN 94/14)
            '/\b\d{4,}\/\d{2,}/i', // Case numbers (e.g., 2024/123)
            '/čl\.\s*\d+/i', // Article references (čl. 15)
            '/st\.\s*\d+/i', // Paragraph references (st. 2)
        ];

        $citationCount = 0;
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $output, $matches);
            $citationCount += count($matches[0]);
        }

        // Check for explicit citation markers
        $hasReferences = stripos($output, 'zakon') !== false ||
                        stripos($output, 'odluka') !== false ||
                        stripos($output, 'presuda') !== false ||
                        stripos($output, 'rješenje') !== false;

        $score = 0;

        if ($citationCount >= 5) {
            $score = 1.0;
        } elseif ($citationCount >= 3) {
            $score = 0.8;
        } elseif ($citationCount >= 1) {
            $score = 0.6;
        } elseif ($hasReferences) {
            $score = 0.4;
        } else {
            $score = 0.2;
        }

        return [
            'score' => $score,
            'passed' => $score >= 0.6,
            'citation_count' => $citationCount,
            'has_references' => $hasReferences,
            'feedback' => $citationCount === 0
                ? 'No specific legal citations found. Include law numbers, case references, or article numbers.'
                : "Found {$citationCount} citation(s). Good practice.",
        ];
    }

    /**
     * Check if the output is relevant to the objective
     */
    protected function evaluateRelevance(AgentRun $run, string $output): array
    {
        $prompt = $this->buildEvaluationPrompt(
            'relevance',
            $run->objective,
            $output,
            'Is this research output relevant and focused on the objective? Are there off-topic sections?'
        );

        $result = $this->getLLMEvaluation($prompt);

        return [
            'score' => $result['score'],
            'passed' => $result['score'] >= 0.7,
            'feedback' => $result['feedback'],
            'off_topic_sections' => $result['off_topic_sections'] ?? [],
        ];
    }

    /**
     * Check the overall quality of the output
     */
    protected function evaluateQuality(string $output): array
    {
        $wordCount = str_word_count($output);
        $hasStructure = preg_match('/^#+\s+/m', $output); // Check for markdown headers
        $hasSections = substr_count(strtolower($output), '##') >= 2;
        $hasLists = preg_match('/^\s*[-*\d]+\.\s+/m', $output);

        $qualityScore = 0;

        // Length check
        if ($wordCount >= 200) {
            $qualityScore += 0.3;
        } elseif ($wordCount >= 100) {
            $qualityScore += 0.2;
        } elseif ($wordCount >= 50) {
            $qualityScore += 0.1;
        }

        // Structure check
        if ($hasStructure) {
            $qualityScore += 0.2;
        }
        if ($hasSections) {
            $qualityScore += 0.3;
        }
        if ($hasLists) {
            $qualityScore += 0.2;
        }

        $feedback = [];
        if ($wordCount < 100) {
            $feedback[] = 'Output is too brief. Provide more detailed analysis.';
        }
        if (!$hasStructure) {
            $feedback[] = 'Add structured sections with clear headers.';
        }
        if (!$hasLists) {
            $feedback[] = 'Use lists to organize findings clearly.';
        }

        return [
            'score' => min($qualityScore, 1.0),
            'passed' => $qualityScore >= 0.7,
            'word_count' => $wordCount,
            'has_structure' => $hasStructure,
            'has_sections' => $hasSections,
            'has_lists' => $hasLists,
            'feedback' => empty($feedback) ? 'Output quality is good.' : implode(' ', $feedback),
        ];
    }

    /**
     * Check if sufficient evidence is provided for claims
     */
    protected function evaluateEvidence(AgentRun $run, string $output): array
    {
        $iterations = $run->iterations ?? [];
        $totalActions = 0;
        $successfulActions = 0;

        foreach ($iterations as $iteration) {
            if (isset($iteration['actions'])) {
                foreach ($iteration['actions'] as $action) {
                    $totalActions++;
                    if ($action['success'] ?? false) {
                        $successfulActions++;
                    }
                }
            }
        }

        $evidenceScore = 0;

        if ($successfulActions >= 10) {
            $evidenceScore = 1.0;
        } elseif ($successfulActions >= 5) {
            $evidenceScore = 0.8;
        } elseif ($successfulActions >= 3) {
            $evidenceScore = 0.6;
        } elseif ($successfulActions >= 1) {
            $evidenceScore = 0.4;
        }

        $missingEvidence = [];
        if ($successfulActions < 3) {
            $missingEvidence[] = 'Insufficient research actions performed. Conduct more searches.';
        }

        return [
            'score' => $evidenceScore,
            'passed' => $evidenceScore >= 0.6,
            'total_actions' => $totalActions,
            'successful_actions' => $successfulActions,
            'missing_evidence' => $missingEvidence,
            'feedback' => empty($missingEvidence)
                ? "Adequate evidence from {$successfulActions} research action(s)."
                : implode(' ', $missingEvidence),
        ];
    }

    /**
     * Build an evaluation prompt for LLM
     */
    protected function buildEvaluationPrompt(
        string $category,
        string $objective,
        string $output,
        string $question
    ): string {
        return <<<PROMPT
You are evaluating a legal research output for {$category}.

OBJECTIVE:
{$objective}

RESEARCH OUTPUT:
{$output}

EVALUATION QUESTION:
{$question}

Provide your evaluation as a JSON object with:
- score: float between 0 and 1
- feedback: brief explanation
- missing_aspects: array of specific missing elements (if applicable)
- off_topic_sections: array of off-topic parts (if applicable)

Respond ONLY with valid JSON.
PROMPT;
    }

    /**
     * Get evaluation from LLM
     */
    protected function getLLMEvaluation(string $prompt): array
    {
        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a legal research evaluator. Provide objective, structured evaluations in JSON format.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $evaluation = json_decode($content, true);

            return [
                'score' => (float)($evaluation['score'] ?? 0.5),
                'feedback' => $evaluation['feedback'] ?? 'No feedback provided.',
                'missing_aspects' => $evaluation['missing_aspects'] ?? [],
                'off_topic_sections' => $evaluation['off_topic_sections'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('LLM evaluation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'score' => 0.5,
                'feedback' => 'Evaluation failed: ' . $e->getMessage(),
                'missing_aspects' => [],
                'off_topic_sections' => [],
            ];
        }
    }

    /**
     * Generate a detailed evaluation report
     */
    public function generateReport(int $runId): string
    {
        $run = AgentRun::find($runId);

        if (!$run) {
            return "Run not found.";
        }

        $evaluation = $this->evaluateRun($runId, $run->final_output ?? '');

        $report = "# Evaluation Report: Run #{$runId}\n\n";
        $report .= "## Overview\n\n";
        $report .= "- **Objective**: {$run->objective}\n";
        $report .= "- **Status**: {$run->status}\n";
        $report .= "- **Overall Score**: {$evaluation['score']} / 1.0\n";
        $report .= "- **Passed**: " . ($evaluation['passed'] ? 'Yes' : 'No') . "\n";
        $report .= "- **Threshold**: {$run->threshold}\n\n";

        $report .= "## Evaluation Criteria\n\n";

        foreach ($evaluation['checks'] as $category => $check) {
            $status = ($check['passed'] ?? false) ? '✓' : '✗';
            $report .= "### {$status} " . ucfirst($category) . "\n\n";
            $report .= "- **Score**: {$check['score']}\n";
            $report .= "- **Feedback**: {$check['feedback']}\n";

            if (!empty($check['missing_aspects'])) {
                $report .= "- **Missing**: " . implode(', ', $check['missing_aspects']) . "\n";
            }
            if (!empty($check['missing_evidence'])) {
                $report .= "- **Missing Evidence**: " . implode(', ', $check['missing_evidence']) . "\n";
            }

            $report .= "\n";
        }

        $report .= "## Recommendations\n\n";

        if (!$evaluation['passed']) {
            $report .= "This run did not meet the quality threshold. Consider:\n\n";

            foreach ($evaluation['checks'] as $category => $check) {
                if (!($check['passed'] ?? false)) {
                    $report .= "- Improve **{$category}**: {$check['feedback']}\n";
                }
            }
        } else {
            $report .= "This run met all quality criteria. Well done!\n";
        }

        return $report;
    }
}
