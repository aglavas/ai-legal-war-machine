<?php

namespace App\Tools;

use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Odluke Download Tool for Vizra ADK
 */
class OdlukeDownloadTool extends BaseTool
{
    protected string $name = 'odluke_download';
    protected string $description = 'Preuzmi odluku (PDF/HTML). Parametri: id (GUID), format {pdf|html|both}, save, base_url';

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
        ];
    }

    public function execute(array $arguments): string
    {
        $result = $this->client->callTool('odluke-download', $arguments);

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
