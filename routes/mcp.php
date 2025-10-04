<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Facades\Mcp;

// Tool: search - jednostavni pretrazivac lokalnih .md/.txt fajlova
Mcp::tool(function (string $query, int $limit = 5): array {
    $files = collect(Storage::files('docs'))
        ->filter(fn ($f) => preg_match('/\.(md|txt)$/i', $f))
        ->map(function ($f) use ($query) {
            $content = Storage::get($f);
            $pos = stripos($content, $query);
            if ($pos === false) return null;
            $start = max(0, $pos - 40);
            $snippet = substr($content, $start, 160);
            return [
                'id' => $f,
                'title' => basename($f),
                'url' => "docs://{$f}",
                'snippet' => $snippet,
            ];
        })
        ->filter()
        ->slice(0, $limit)
        ->values()
        ->all();

    return [
        'results' => $files,
        'count' => count($files),
    ];
})
    ->name('search')
    ->description('Full-text search of local docs (storage/app/docs)')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Search query'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
        ],
        'required' => ['query'],
    ]);

// Tool: fetch - dohvaca sadrzaj dokumenta po relativnoj putanji
Mcp::tool(function (string $path): array {
    if (!preg_match('#^[A-Za-z0-9/_\.-]+$#', $path)) {
        throw new InvalidArgumentException('Invalid path');
    }
    $full = "docs/{$path}";
    if (!Storage::exists($full)) {
        throw new InvalidArgumentException("Not found: {$path}");
    }
    $content = Storage::get($full);
    return [
        'id' => $path,
        'mimeType' => 'text/markdown',
        'text' => $content,
    ];
})
    ->name('fetch')
    ->description('Fetches a doc by relative path under storage/app/docs')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'Relative path within docs/, e.g. intro.md'],
        ],
        'required' => ['path'],
    ]);

// Resource template: docs://{path}
Mcp::resourceTemplate('docs://{path}', function (string $path): string {
    $path = ltrim($path, '/');
    if (!preg_match('#^[A-Za-z0-9/_\.-]+$#', $path)) {
        throw new InvalidArgumentException('Invalid path');
    }
    $full = "docs/{$path}";
    if (!Storage::exists($full)) {
        throw new InvalidArgumentException("Not found: {$path}");
    }
    return Storage::get($full);
})
    ->name('doc_resource')
    ->description('Read a local doc as a resource')
    ->mimeType('text/markdown');


Mcp::tool(function (string $user_query): array {
    // Pseudo: koristite vašu klasu agenta
    $reply = \App\Vizra\Agents\SupportAgent::run($user_query)->go();
    return ['text' => (string)$reply];
})
    ->name('ask_support_agent')
    ->description('Runs the SupportAgent on the given user_query')
    ->inputSchema([
        'type' => 'object',
        'properties' => ['user_query' => ['type' => 'string']],
        'required' => ['user_query'],
    ]);
