<?php

namespace App\Tools;

use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Odluke Meta Tool for Vizra ADK
 */
class OdlukeMetaTool extends BaseTool
{
    protected string $name = 'odluke_meta';
    protected string $description = 'Dohvati metapodatke za jedan ili više ID-eva odluka (Document/View?id=...)';

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
        ];
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
