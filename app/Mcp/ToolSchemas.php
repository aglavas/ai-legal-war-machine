<?php

namespace App\Mcp;

/**
 * Centralized Tool Schema Definitions
 *
 * Single source of truth for all MCP tool schemas.
 * This prevents duplication across MCP attributes, OpenAI functions, and Vizra ADK tools.
 */
class ToolSchemas
{
    /**
     * Get all tool schemas
     *
     * @return array<string, array{name: string, description: string, inputSchema: array}>
     */
    public static function all(): array
    {
        return [
            'odluke-search' => self::odlukeSearch(),
            'odluke-meta' => self::odlukeMeta(),
            'odluke-download' => self::odlukeDownload(),
            'law-articles-search' => self::lawArticlesSearch(),
            'law-article-by-id' => self::lawArticleById(),
        ];
    }

    /**
     * Get schema for a specific tool
     *
     * @param string $toolName
     * @return array{name: string, description: string, inputSchema: array}|null
     */
    public static function get(string $toolName): ?array
    {
        return self::all()[$toolName] ?? null;
    }

    /**
     * Odluke Search Tool Schema
     */
    public static function odlukeSearch(): array
    {
        return [
            'name' => 'odluke-search',
            'description' => 'Pretraži odluke i vrati ID-eve s /Document/DisplayList. Parametri: q, params, page, limit, base_url',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'q' => [
                        'type' => 'string',
                        'description' => 'Search query text',
                    ],
                    'params' => [
                        'type' => 'string',
                        'description' => 'Additional query parameters',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (default: 100)',
                        'minimum' => 1,
                        'maximum' => 500,
                    ],
                    'page' => [
                        'type' => 'integer',
                        'description' => 'Page number (default: 1)',
                        'minimum' => 1,
                    ],
                    'base_url' => [
                        'type' => 'string',
                        'description' => 'Custom base URL (optional)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Odluke Meta Tool Schema
     */
    public static function odlukeMeta(): array
    {
        return [
            'name' => 'odluke-meta',
            'description' => 'Dohvati metapodatke za jedan ili više ID-eva odluka (Document/View?id=...)',
            'inputSchema' => [
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
                    'base_url' => [
                        'type' => 'string',
                        'description' => 'Custom base URL (optional)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Odluke Download Tool Schema
     */
    public static function odlukeDownload(): array
    {
        return [
            'name' => 'odluke-download',
            'description' => 'Preuzmi odluku (PDF/HTML). Parametri: id (GUID), format {pdf|html|both}, save, base_url',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'Decision ID (GUID)',
                    ],
                    'format' => [
                        'type' => 'string',
                        'enum' => ['pdf', 'html', 'both'],
                        'description' => 'Format preuzimanja (default: pdf)',
                    ],
                    'save' => [
                        'type' => 'boolean',
                        'description' => 'Snimi lokalno u storage/app/odluke (default: false)',
                    ],
                    'base_url' => [
                        'type' => 'string',
                        'description' => 'Custom base URL (optional)',
                    ],
                ],
                'required' => ['id'],
            ],
        ];
    }

    /**
     * Law Articles Search Tool Schema
     */
    public static function lawArticlesSearch(): array
    {
        return [
            'name' => 'law-articles-search',
            'description' => 'Pretraži zakone i članke zakona. Parametri: query (opcionalni text za pretragu), law_number, title, limit',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Optional search text for searching across multiple fields',
                    ],
                    'law_number' => [
                        'type' => 'string',
                        'description' => 'Filter by law number',
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Filter by law title',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of laws to return (default: 10, max: 100)',
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                ],
            ],
        ];
    }

    /**
     * Law Article By ID Tool Schema
     */
    public static function lawArticleById(): array
    {
        return [
            'name' => 'law-article-by-id',
            'description' => 'Dohvati jedan članak zakona po ID-u',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'Law article ID',
                    ],
                ],
                'required' => ['id'],
            ],
        ];
    }

    /**
     * Convert to OpenAI function format
     *
     * @param string $toolName
     * @return array|null
     */
    public static function toOpenAIFunction(string $toolName): ?array
    {
        $schema = self::get($toolName);
        if (!$schema) {
            return null;
        }

        // Convert snake_case to camelCase for OpenAI function names
        $functionName = str_replace('-', '_', $schema['name']);

        return [
            'type' => 'function',
            'function' => [
                'name' => $functionName,
                'description' => $schema['description'],
                'parameters' => $schema['inputSchema'],
            ],
        ];
    }

    /**
     * Get all tools in OpenAI function format
     *
     * @return array
     */
    public static function allOpenAIFunctions(): array
    {
        return array_values(array_filter(array_map(
            fn($name) => self::toOpenAIFunction($name),
            array_keys(self::all())
        )));
    }
}
