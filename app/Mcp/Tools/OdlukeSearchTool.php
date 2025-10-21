<?php

namespace App\Mcp\Tools;

use App\Services\Odluke\OdlukeClient;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class OdlukeSearchTool extends Tool
{
    protected string $name = 'odluke-search';
    protected string $title = 'Pretraži odluke (lista)';
    protected string $description = 'Dohvaća ID-eve odluka s /Document/DisplayList uz opcionalne filtere (q, params, page, limit).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'q'        => $schema->string()->description('Slobodni upit za pretragu (npr. "ugovor o radu")'),
            'params'   => $schema->string()->description('Dodatni query string za /Document/DisplayList, npr. "sort=dat&vo=Presuda"'),
            'limit'    => $schema->integer()->minimum(1)->maximum(500)->default(50)
                ->description('Maksimalan broj ID-eva koje treba vratiti s jedne stranice'),
            'page'     => $schema->integer()->minimum(1)->default(1)->description('Broj stranice rezultata'),
            'base_url' => $schema->string()->description('Custom base URL (default iz config/odluke.php)'),
        ];
    }

    public function handle(array $arguments): ToolResult|\Generator
    {
        $client = OdlukeClient::fromConfig()->withBaseUrl($arguments['base_url'] ?? null);
        $out = $client->collectIdsFromList(
            $arguments['q'] ?? null,
            $arguments['params'] ?? null,
            (int) ($arguments['limit'] ?? 100),
            (int) ($arguments['page'] ?? 1),
        );

        if (($out['ids'] ?? []) === []) {
            return ToolResult::error('Nema ID-eva za zadane parametre ili dohvat nije uspio.');
        }

        return ToolResult::text(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function description(): string
    {
        return 'Searches and returns a list of decision IDs based on the provided query and parameters.';
    }
}
