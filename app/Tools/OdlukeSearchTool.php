<?php

namespace App\Tools;

use App\Mcp\ToolSchemas;
use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Odluke Search Tool for Vizra ADK
 *
 * This wraps the internal MCP client to make odluke-search available
 * as a native Vizra ADK tool (no HTTP required).
 */
class OdlukeSearchTool extends BaseTool
{
    protected string $name = 'odluke_search';
    protected string $description;

    protected InternalMcpClient $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = app(InternalMcpClient::class);

        // Get description from centralized schema
        $schema = ToolSchemas::get('odluke-search');
        $this->description = $schema['description'] ?? 'Odluke Search Tool';
    }

    public function getInputSchema(): array
    {
        // Use centralized schema definition
        $schema = ToolSchemas::get('odluke-search');
        return $schema['inputSchema'] ?? ['type' => 'object'];
    }

    public function execute(array $arguments): string
    {
        $result = $this->client->callTool('odluke-search', $arguments);

        // Extract text from MCP content format
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
