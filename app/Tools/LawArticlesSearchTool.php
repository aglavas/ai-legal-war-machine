<?php

namespace App\Tools;

use App\Mcp\ToolSchemas;
use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Law Articles Search Tool for Vizra ADK
 */
class LawArticlesSearchTool extends BaseTool
{
    protected string $name = 'law_articles_search';
    protected string $description;

    protected InternalMcpClient $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = app(InternalMcpClient::class);

        // Get description from centralized schema
        $schema = ToolSchemas::get('law-articles-search');
        $this->description = $schema['description'] ?? 'Law Articles Search Tool';
    }

    public function getInputSchema(): array
    {
        // Use centralized schema definition
        $schema = ToolSchemas::get('law-articles-search');
        return $schema['inputSchema'] ?? ['type' => 'object'];
    }

    public function execute(array $arguments): string
    {
        $result = $this->client->callTool('law-articles-search', $arguments);

        $text = '';
        if (isset($result['content']) && is_array($result['content'])) {
            foreach ($result['content'] as $item) {
                if (isset($item['text'])) {
                    $text .= $item['text'];
                }
            }
        }

        return $text ?: json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
