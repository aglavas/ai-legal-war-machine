<?php

namespace App\Tools;

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
    protected string $description = 'Pretraži odluke i vrati ID-eve s /Document/DisplayList. Parametri: q, params, page, limit, base_url';

    protected InternalMcpClient $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = app(InternalMcpClient::class);
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'q' => [
                    'type' => 'string',
                    'description' => 'Slobodni upit za pretragu (npr. "ugovor o radu")',
                ],
                'params' => [
                    'type' => 'string',
                    'description' => 'Dodatni query string za /Document/DisplayList, npr. "sort=dat&vo=Presuda"',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maksimalan broj ID-eva (1-500, default 100)',
                    'minimum' => 1,
                    'maximum' => 500,
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Broj stranice rezultata (default 1)',
                    'minimum' => 1,
                ],
                'base_url' => [
                    'type' => 'string',
                    'description' => 'Custom base URL (optional)',
                ],
            ],
        ];
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
