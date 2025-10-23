<?php

namespace App\Services\Mcp;

use App\Mcp\OdlukeTools;
use Illuminate\Support\Facades\Log;

/**
 * Internal MCP Client for Odluke Tools
 *
 * This provides a direct in-process way to call MCP tools without HTTP overhead.
 * Used by OdlukeAgent when running via dashboard or artisan commands.
 *
 * This is more efficient and reliable than HTTP transport for internal use.
 */
class InternalMcpClient
{
    protected OdlukeTools $tools;

    public function __construct()
    {
        $this->tools = new OdlukeTools();
    }

    /**
     * Call an MCP tool directly (bypassing HTTP)
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        Log::info('Internal MCP: Calling tool', [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
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

            Log::info('Internal MCP: Tool executed successfully', [
                'tool' => $toolName,
                'has_error' => $result['isError'] ?? false,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Internal MCP: Tool execution failed', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return [
                'content' => [[
                    'type' => 'text',
                    'text' => 'Error: ' . $e->getMessage(),
                ]],
                'isError' => true,
            ];
        }
    }

    /**
     * List all available tools
     */
    public function listTools(): array
    {
        return [
            [
                'name' => 'odluke-search',
                'description' => 'Pretraži odluke i vrati ID-eve s /Document/DisplayList. Parametri: q, params, page, limit, base_url',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Slobodni upit za pretragu'],
                        'params' => ['type' => 'string', 'description' => 'Dodatni query string za filtere'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                        'base_url' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'odluke-meta',
                'description' => 'Dohvati metapodatke za jedan ili više ID-eva',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Single decision ID'],
                        'ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'base_url' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'odluke-download',
                'description' => 'Preuzmi odluku (PDF/HTML)',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Decision ID'],
                        'format' => ['type' => 'string', 'enum' => ['pdf', 'html', 'both']],
                        'save' => ['type' => 'boolean'],
                        'base_url' => ['type' => 'string'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'law-articles-search',
                'description' => 'Pretraži zakone i članke zakona',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'law_number' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                ],
            ],
            [
                'name' => 'law-article-by-id',
                'description' => 'Dohvati jedan članak zakona po ID-u',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Law article ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ];
    }

    /**
     * Get information about this MCP client
     */
    public function getInfo(): array
    {
        return [
            'name' => 'Internal Odluke MCP Client',
            'version' => '1.0.0',
            'transport' => 'direct',
            'tools' => array_column($this->listTools(), 'name'),
        ];
    }
}
