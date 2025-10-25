<?php

namespace App\Http\Controllers;

use App\Services\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function __construct(
        protected UnifiedSearchService $searchService
    ) {
    }

    /**
     * Unified search across all legal corpora
     *
     * POST /api/search
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'corpora' => 'sometimes|array',
            'corpora.*' => 'in:laws,decisions,cases',
            'weights' => 'sometimes|array',
            'weights.laws' => 'sometimes|numeric|min:0|max:10',
            'weights.decisions' => 'sometimes|numeric|min:0|max:10',
            'weights.cases' => 'sometimes|numeric|min:0|max:10',
            'filters' => 'sometimes|array',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.country' => 'sometimes|string',
            'filters.court' => 'sometimes|string',
            'filters.decision_type' => 'sometimes|string',
            'filters.category' => 'sometimes|string',
            'filters.language' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
            'model' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = $request->input('query');
            $options = [
                'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                'weights' => $request->input('weights', [
                    'laws' => 1.0,
                    'decisions' => 1.0,
                    'cases' => 1.0,
                ]),
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
                'model' => $request->input('model', config('openai.models.embeddings')),
            ];

            $results = $this->searchService->search($query, $options);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search laws only
     *
     * POST /api/search/laws
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchLaws(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'filters' => 'sometimes|array',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.country' => 'sometimes|string',
            'filters.language' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->searchService->search($request->input('query'), [
                'corpora' => ['laws'],
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search court decisions only
     *
     * POST /api/search/decisions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchDecisions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'filters' => 'sometimes|array',
            'filters.court' => 'sometimes|string',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.decision_type' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->searchService->search($request->input('query'), [
                'corpora' => ['decisions'],
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search case documents only
     *
     * POST /api/search/cases
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchCases(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'filters' => 'sometimes|array',
            'filters.category' => 'sometimes|string',
            'filters.language' => 'sometimes|string',
            'filters.source' => 'sometimes|string',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->searchService->search($request->input('query'), [
                'corpora' => ['cases'],
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hybrid search (vector + keyword)
     *
     * POST /api/search/hybrid
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function hybridSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'corpora' => 'sometimes|array',
            'corpora.*' => 'in:laws,decisions,cases',
            'filters' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->searchService->hybridSearch($request->input('query'), [
                'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Hybrid search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search with citation context
     *
     * POST /api/search/with-citations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchWithCitations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:1000',
            'corpora' => 'sometimes|array',
            'corpora.*' => 'in:laws,decisions,cases',
            'filters' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:100',
            'threshold' => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->searchService->searchWithCitations($request->input('query'), [
                'corpora' => $request->input('corpora', ['laws', 'decisions', 'cases']),
                'filters' => $request->input('filters', []),
                'limit' => $request->input('limit', 10),
                'threshold' => $request->input('threshold', 0.7),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Citation search failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
