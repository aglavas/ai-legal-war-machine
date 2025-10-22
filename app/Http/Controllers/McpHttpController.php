<?php

namespace App\Http\Controllers;

use App\Mcp\OdlukeTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * MCP HTTP Endpoint Controller
 *
 * This controller exposes MCP tools via HTTP transport for use by:
 * - Vizra ADK agents (like OdlukeAgent)
 * - External MCP clients
 * - Other services that need to call MCP tools via HTTP
 *
 * It implements the MCP protocol over HTTP using JSON-RPC 2.0 format.
 */
class McpHttpController extends Controller
{
    protected OdlukeTools $tools;

    public function __construct()
    {
        $this->tools = new OdlukeTools();
    }

    /**
     * POST /mcp/message
     *
     * Main endpoint for MCP protocol messages.
     * Accepts JSON-RPC 2.0 formatted MCP requests and returns MCP responses.
     *
     * Expected request format:
     * {
     *   "jsonrpc": "2.0",
     *   "id": "request-id",
     *   "method": "tools/call",
     *   "params": {
     *     "name": "tool-name",
     *     "arguments": { ... }
     *   }
     * }
     */
    public function message(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('MCP HTTP: Received message', [
            'method' => $payload['method'] ?? null,
            'id' => $payload['id'] ?? null,
        ]);

        try {
            // Handle different MCP protocol methods
            $method = $payload['method'] ?? '';
            $id = $payload['id'] ?? null;
            $params = $payload['params'] ?? [];

            $response = match ($method) {
                'tools/list' => $this->listTools($id),
                'tools/call' => $this->callTool($id, $params),
                'resources/list' => $this->listResources($id),
                'prompts/list' => $this->listPrompts($id),
                'initialize' => $this->initialize($id, $params),
                default => [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method not found: {$method}",
                    ],
                ],
            };

            Log::info('MCP HTTP: Sending response', [
                'id' => $id,
                'has_error' => isset($response['error']),
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('MCP HTTP: Request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $payload['id'] ?? null,
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Handle tools/list request
     */
    protected function listTools($id): array
    {
        $tools = [
            [
                'name' => 'odluke-search',
                'description' => 'Pretraži odluke i vrati ID-eve s /Document/DisplayList. Parametri: q, params, page, limit, base_url',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Slobodni upit za pretragu'],
                        'params' => ['type' => 'string', 'description' => 'Dodatni query string za filtere'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Maksimalan broj rezultata'],
                        'page' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Broj stranice'],
                        'base_url' => ['type' => 'string', 'description' => 'Custom base URL'],
                    ],
                ],
            ],
            [
                'name' => 'odluke-meta',
                'description' => 'Dohvati metapodatke za jedan ili više ID-eva (Document/View?id=...)',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Single decision ID (GUID)'],
                        'ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Array of decision IDs'],
                        'base_url' => ['type' => 'string', 'description' => 'Custom base URL'],
                    ],
                ],
            ],
            [
                'name' => 'odluke-download',
                'description' => 'Preuzmi odluku (PDF/HTML). Parametri: id (GUID), format {pdf|html|both}, save, base_url',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Decision ID (GUID)'],
                        'format' => ['type' => 'string', 'enum' => ['pdf', 'html', 'both'], 'description' => 'Download format'],
                        'save' => ['type' => 'boolean', 'description' => 'Save locally'],
                        'base_url' => ['type' => 'string', 'description' => 'Custom base URL'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'law-articles-search',
                'description' => 'Pretraži zakone i članke zakona. Parametri: query, law_number, title, limit',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Opcionalni text za pretragu'],
                        'law_number' => ['type' => 'string', 'description' => 'Broj zakona'],
                        'title' => ['type' => 'string', 'description' => 'Naslov zakona'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Broj rezultata'],
                    ],
                ],
            ],
            [
                'name' => 'law-article-by-id',
                'description' => 'Dohvati jedan članak zakona po ID-u',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Law article ID (ULID)'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ];

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => $tools,
            ],
        ];
    }

    /**
     * Handle tools/call request
     */
    protected function callTool($id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        Log::info('MCP HTTP: Calling tool', [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
            // Call the appropriate tool method
            $result = match ($toolName) {
                'odluke-search' => $this->tools->search(
                    $arguments['q'] ?? null,
                    $arguments['params'] ?? null,
                    $arguments['limit'] ?? 100,
                    $arguments['page'] ?? 1,
                    $arguments['base_url'] ?? null
                ),
                'odluke-meta' => $this->tools->meta(
                    $arguments['id'] ?? null,
                    $arguments['ids'] ?? null,
                    $arguments['base_url'] ?? null
                ),
                'odluke-download' => $this->tools->download(
                    $arguments['id'] ?? '',
                    $arguments['format'] ?? 'pdf',
                    $arguments['save'] ?? false,
                    $arguments['base_url'] ?? null
                ),
                'law-articles-search' => $this->tools->searchLawArticles(
                    $arguments['query'] ?? null,
                    $arguments['law_number'] ?? null,
                    $arguments['title'] ?? null,
                    $arguments['limit'] ?? 10
                ),
                'law-article-by-id' => $this->tools->getLawArticleById(
                    $arguments['id'] ?? ''
                ),
                default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
            };

            // MCP tools return content in format: ['content' => [...], 'isError' => bool]
            // Convert to MCP protocol response
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => $result['content'] ?? [],
                    'isError' => $result['isError'] ?? false,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('MCP HTTP: Tool call failed', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Handle resources/list request
     */
    protected function listResources($id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'resources' => [],
            ],
        ];
    }

    /**
     * Handle prompts/list request
     */
    protected function listPrompts($id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'prompts' => [],
            ],
        ];
    }

    /**
     * Handle initialize request
     */
    protected function initialize($id, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '1.0.0',
                'serverInfo' => [
                    'name' => 'AI Legal War Machine MCP Server',
                    'version' => '1.0.0',
                ],
                'capabilities' => [
                    'tools' => [],
                ],
            ],
        ];
    }

    /**
     * GET /mcp/info
     *
     * Information endpoint (for debugging/monitoring)
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'name' => 'AI Legal War Machine MCP Server',
            'version' => '1.0.0',
            'protocol' => 'MCP 1.0.0',
            'transport' => 'http',
            'tools' => [
                'odluke-search',
                'odluke-meta',
                'odluke-download',
                'law-articles-search',
                'law-article-by-id',
            ],
            'tools_count' => 5,
            'endpoints' => [
                'message' => 'POST /mcp/message - MCP protocol endpoint',
                'info' => 'GET /mcp/info - Server information',
            ],
            'usage' => [
                'agent_config' => 'Configure in vizra-adk.php mcp_servers.odluke',
                'url' => url('/mcp/message'),
            ],
        ]);
    }
}
