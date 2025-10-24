<?php

namespace App\Tools;

use App\Mcp\ToolSchemas;
use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Law Article By ID Tool for Vizra ADK
 */
class LawArticleByIdTool extends BaseTool
{
    protected string $name = 'law_article_by_id';
    protected string $description;

    protected InternalMcpClient $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = app(InternalMcpClient::class);

        // Get description from centralized schema
        $schema = ToolSchemas::get('law-article-by-id');
        $this->description = $schema['description'] ?? 'Law Article By ID Tool';
    }

    public function getInputSchema(): array
    {
        // Use centralized schema definition
        $schema = ToolSchemas::get('law-article-by-id');
        return $schema['inputSchema'] ?? ['type' => 'object'];
    }

    public function execute(array $arguments): string
    {
        $result = $this->client->callTool('law-article-by-id', $arguments);

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
