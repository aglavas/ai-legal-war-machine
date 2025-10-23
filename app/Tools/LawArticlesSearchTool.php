<?php

namespace App\Tools;

use App\Services\Mcp\InternalMcpClient;
use Vizra\VizraADK\Tools\BaseTool;

/**
 * Law Articles Search Tool for Vizra ADK
 */
class LawArticlesSearchTool extends BaseTool
{
    protected string $name = 'law_articles_search';
    protected string $description = 'Pretraži zakone i članke zakona. Parametri: query, law_number, title, limit';

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
                'query' => [
                    'type' => 'string',
                    'description' => 'Opcionalni text za pretragu kroz zakone',
                ],
                'law_number' => [
                    'type' => 'string',
                    'description' => 'Broj zakona (npr. "NN 123/20")',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Naslov zakona',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Broj rezultata (1-100, default: 10)',
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ];
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
