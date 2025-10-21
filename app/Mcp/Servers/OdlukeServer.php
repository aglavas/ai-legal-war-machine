<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\OdlukeSearchTool;
use App\Mcp\Tools\OdlukeFetchMetaTool;
use App\Mcp\Tools\OdlukeDownloadTool;

class OdlukeServer extends Server
{
    protected string $name        = 'Odluke RH';
    protected string $version     = '1.0.0';
    public string $instructions = 'MCP server za pretragu i preuzimanje sudskih odluka s odluke.sudovi.hr.';

    public array $tools = [
        OdlukeSearchTool::class,
        OdlukeFetchMetaTool::class,
        OdlukeDownloadTool::class,
    ];

    // resources / prompts po potrebi...
}
