<?php

namespace App\Services;

use App\Mcp\OdlukeTools;
use Illuminate\Support\Facades\Log;
use PhpMcp\Server\Server as McpServer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Bridge service that exposes MCP tools as OpenAI-compatible functions.
 * This allows OpenAI's GPT models to discover and use MCP tools via function calling.
 */
class McpToOpenAIBridge
{
    protected OdlukeTools $odlukeTools;

    public function __construct()
    {
        $this->odlukeTools = new OdlukeTools();
    }

    /**
     * Get all available tools in OpenAI function format
     */
    public function getOpenAIFunctions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odluke_search',
                    'description' => 'Search Odluke (judicial decisions) from odluke.sudovi.hr and return decision IDs with optional filters (q, params, page, limit)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Free text search query (e.g., "ugovor o radu")',
                            ],
                            'params' => [
                                'type' => 'string',
                                'description' => 'Additional query string for filters (e.g., "sort=dat&vo=Presuda")',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of IDs to return from one page (1-500, default 100)',
                                'minimum' => 1,
                                'maximum' => 500,
                            ],
                            'page' => [
                                'type' => 'integer',
                                'description' => 'Page number of results (default 1)',
                                'minimum' => 1,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odluke_meta',
                    'description' => 'Fetch metadata for one or more decision IDs from odluke.sudovi.hr',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => 'Single decision ID (GUID)',
                            ],
                            'ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Array of decision IDs',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'odluke_download',
                    'description' => 'Download decision PDF/HTML from odluke.sudovi.hr. Can save locally or return info about the download.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => 'Decision ID (GUID)',
                            ],
                            'format' => [
                                'type' => 'string',
                                'enum' => ['pdf', 'html', 'both'],
                                'description' => 'Format to download (pdf, html, or both)',
                            ],
                            'save' => [
                                'type' => 'boolean',
                                'description' => 'Whether to save files locally (storage/app/odluke)',
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'law_articles_search',
                    'description' => 'Search ingested Croatian laws and law articles by query text, law number, or title. Returns laws with their articles/chunks.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Optional free text search across doc_id, title, law_number, jurisdiction, keywords',
                            ],
                            'law_number' => [
                                'type' => 'string',
                                'description' => 'Filter by specific law number (e.g., "NN 123/20")',
                            ],
                            'title' => [
                                'type' => 'string',
                                'description' => 'Filter by law title',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Number of laws to return (1-100, default 10)',
                                'minimum' => 1,
                                'maximum' => 100,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'law_article_by_id',
                    'description' => 'Get a single law article/chunk by its ID with full details including content, metadata, and parent law information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => 'Law article ID (ULID)',
                            ],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call from OpenAI and return the result
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        Log::info('MCP Bridge: Executing tool', [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
            $result = match ($toolName) {
                'odluke_search' => $this->odlukeTools->search(
                    $arguments['q'] ?? null,
                    $arguments['params'] ?? null,
                    $arguments['limit'] ?? 100,
                    $arguments['page'] ?? 1,
                    $arguments['base_url'] ?? null
                ),
                'odluke_meta' => $this->odlukeTools->meta(
                    $arguments['id'] ?? null,
                    $arguments['ids'] ?? null,
                    $arguments['base_url'] ?? null
                ),
                'odluke_download' => $this->odlukeTools->download(
                    $arguments['id'] ?? '',
                    $arguments['format'] ?? 'pdf',
                    $arguments['save'] ?? false,
                    $arguments['base_url'] ?? null
                ),
                'law_articles_search' => $this->odlukeTools->searchLawArticles(
                    $arguments['query'] ?? null,
                    $arguments['law_number'] ?? null,
                    $arguments['title'] ?? null,
                    $arguments['limit'] ?? 10
                ),
                'law_article_by_id' => $this->odlukeTools->getLawArticleById(
                    $arguments['id'] ?? ''
                ),
                default => [
                    'content' => [[
                        'type' => 'text',
                        'text' => "Unknown tool: {$toolName}",
                    ]],
                    'isError' => true,
                ],
            };

            // Extract text content from MCP response format
            $textContent = '';
            if (isset($result['content']) && is_array($result['content'])) {
                foreach ($result['content'] as $item) {
                    if (isset($item['text'])) {
                        $textContent .= $item['text'];
                    }
                }
            }

            return [
                'success' => !($result['isError'] ?? false),
                'content' => $textContent ?: json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'error' => ($result['isError'] ?? false) ? $textContent : null,
            ];
        } catch (\Throwable $e) {
            Log::error('MCP Bridge: Tool execution failed', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'content' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tool definitions for the /v1/tools endpoint (OpenAI format)
     */
    public function getToolDefinitions(): array
    {
        return [
            'object' => 'list',
            'data' => array_map(function ($func) {
                return [
                    'id' => 'tool_' . str_replace('_', '', $func['function']['name']),
                    'type' => 'function',
                    'function' => $func['function'],
                ];
            }, $this->getOpenAIFunctions()),
        ];
    }

    /**
     * Process a chat completion request with tool calls
     */
    public function processChatWithTools(array $messages, array $tools, ?string $model = null): array
    {
        // This would integrate with your OpenAIService to make the actual chat call
        // For now, return the tool definitions for OpenAI to use
        return [
            'tools' => $tools,
            'tool_choice' => 'auto',
        ];
    }
}
