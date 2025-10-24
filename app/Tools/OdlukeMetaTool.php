<?php

namespace App\Tools;

use App\Mcp\ToolSchemas;
use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Odluke Meta Tool for Vizra ADK
 */
class OdlukeMetaTool extends BaseTool
{
    protected string $name = 'odluke_meta';
    protected string $description;

    protected InternalMcpClient $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = app(InternalMcpClient::class);

        // Get description from centralized schema
        $schema = ToolSchemas::get('odluke-meta');
        $this->description = $schema['description'] ?? 'Odluke Meta Tool';
    }

    public function getInputSchema(): array
    {
        // Use centralized schema definition
        $schema = ToolSchemas::get('odluke-meta');
        return $schema['inputSchema'] ?? ['type' => 'object'];
    }

    public function execute(array $arguments): string
    {
        $result = $this->client->callTool('odluke-meta', $arguments);

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
