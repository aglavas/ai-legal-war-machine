<?php

namespace App\Http\Controllers;

use App\Services\McpToOpenAIBridge;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller that exposes MCP tools as OpenAI-compatible function calling endpoints.
 * This allows OpenAI Playground and GPT models to discover and use your MCP tools.
 */
class McpOpenAIController extends Controller
{
    protected McpToOpenAIBridge $bridge;
    protected OpenAIService $openai;

    public function __construct(McpToOpenAIBridge $bridge, OpenAIService $openai)
    {
        $this->bridge = $bridge;
        $this->openai = $openai;
    }

    /**
     * GET /api/mcp-openai/tools
     * Returns list of available MCP tools in OpenAI format
     */
    public function listTools(Request $request): JsonResponse
    {
        return response()->json($this->bridge->getToolDefinitions());
    }

    /**
     * POST /api/mcp-openai/tools/execute
     * Execute a specific tool
     *
     * Request body:
     * {
     *   "tool_name": "odluke_search",
     *   "arguments": { "q": "test", "limit": 10 }
     * }
     */
    public function executeTool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool_name' => 'required|string',
            'arguments' => 'required|array',
        ]);

        $result = $this->bridge->executeTool(
            $validated['tool_name'],
            $validated['arguments']
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'] ?? 'Tool execution failed',
                'tool_name' => $validated['tool_name'],
            ], 400);
        }

        return response()->json([
            'tool_name' => $validated['tool_name'],
            'result' => $result['content'],
        ]);
    }

    /**
     * POST /api/mcp-openai/chat/completions
     * OpenAI-compatible chat completions endpoint that automatically includes MCP tools
     *
     * This endpoint:
     * 1. Accepts standard OpenAI chat completion requests
     * 2. Automatically includes all MCP tools as available functions
     * 3. Forwards to OpenAI API
     * 4. When OpenAI calls a tool, executes it via MCP bridge
     * 5. Continues the conversation with tool results
     */
    public function chatCompletions(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messages = $payload['messages'] ?? [];

        // Add MCP tools to the request if tools not explicitly provided
        if (!isset($payload['tools']) || empty($payload['tools'])) {
            $payload['tools'] = $this->bridge->getOpenAIFunctions();
            $payload['tool_choice'] = $payload['tool_choice'] ?? 'auto';
        }

        // Set default model if not provided
        $payload['model'] = $payload['model'] ?? config('openai.models.chat', 'gpt-4o-mini');

        Log::info('MCP-OpenAI: Chat completion request', [
            'model' => $payload['model'],
            'message_count' => count($messages),
            'tools_count' => count($payload['tools'] ?? []),
        ]);

        try {
            // Extract options from payload
            $model = $payload['model'] ?? config('openai.models.chat', 'gpt-4o-mini');
            $options = array_diff_key($payload, array_flip(['messages', 'model']));

            // Make the initial request to OpenAI
            $response = $this->openai->chat($messages, $model, $options);

            // Check if OpenAI wants to call any tools
            $choice = $response['choices'][0] ?? null;
            $message = $choice['message'] ?? null;
            $toolCalls = $message['tool_calls'] ?? [];

            // If there are tool calls, execute them
            if (!empty($toolCalls)) {
                Log::info('MCP-OpenAI: Processing tool calls', [
                    'count' => count($toolCalls),
                ]);

                // Add assistant's message with tool calls to conversation
                $messages[] = $message;

                // Execute each tool call
                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'] ?? '';
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'] ?? '';

                    Log::info('MCP-OpenAI: Executing tool', [
                        'function' => $functionName,
                        'arguments' => $arguments,
                    ]);

                    // Execute the tool via our bridge
                    $result = $this->bridge->executeTool($functionName, $arguments);

                    // Add tool result to conversation
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'name' => $functionName,
                        'content' => $result['success']
                            ? $result['content']
                            : "Error: " . ($result['error'] ?? 'Unknown error'),
                    ];
                }

                // Make another request to OpenAI with the tool results
                Log::info('MCP-OpenAI: Sending tool results back to OpenAI', [
                    'message_count' => count($messages),
                ]);

                $response = $this->openai->chat($messages, $model, $options);
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('MCP-OpenAI: Chat completion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => 'mcp_bridge_error',
                    'code' => $e->getCode(),
                ],
            ], 500);
        }
    }

    /**
     * GET /api/mcp-openai/info
     * Returns information about the MCP-OpenAI bridge
     */
    public function info(): JsonResponse
    {
        $tools = $this->bridge->getOpenAIFunctions();

        return response()->json([
            'name' => 'MCP-OpenAI Bridge',
            'version' => '1.0.0',
            'description' => 'Exposes MCP tools as OpenAI-compatible function calling endpoints',
            'endpoints' => [
                'tools' => [
                    'method' => 'GET',
                    'path' => '/api/mcp-openai/tools',
                    'description' => 'List all available MCP tools in OpenAI format',
                ],
                'execute' => [
                    'method' => 'POST',
                    'path' => '/api/mcp-openai/tools/execute',
                    'description' => 'Execute a specific tool',
                ],
                'chat' => [
                    'method' => 'POST',
                    'path' => '/api/mcp-openai/chat/completions',
                    'description' => 'OpenAI-compatible chat completions with MCP tools',
                ],
            ],
            'available_tools' => array_map(fn($t) => $t['function']['name'], $tools),
            'tools_count' => count($tools),
        ]);
    }

    /**
     * POST /api/mcp-openai/webhook
     * Webhook endpoint for OpenAI to call when using function calling
     * This can be used as a callback URL in OpenAI's function calling setup
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('MCP-OpenAI: Webhook received', [
            'payload' => $payload,
        ]);

        // Extract function call details
        $functionName = $payload['function_name'] ?? $payload['name'] ?? '';
        $arguments = $payload['arguments'] ?? [];

        if (empty($functionName)) {
            return response()->json([
                'error' => 'Missing function_name',
            ], 400);
        }

        $result = $this->bridge->executeTool($functionName, $arguments);

        return response()->json([
            'function_name' => $functionName,
            'success' => $result['success'],
            'result' => $result['content'],
            'error' => $result['error'],
        ]);
    }
}
