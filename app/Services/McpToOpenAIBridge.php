<?php

namespace App\Services;

use App\Mcp\OdlukeTools;
use App\Mcp\ToolSchemas;
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
     * Uses centralized ToolSchemas for single source of truth
     */
    public function getOpenAIFunctions(): array
    {
        return ToolSchemas::allOpenAIFunctions();
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
