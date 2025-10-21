<?php

namespace App\Mcp\Tools;

use App\Services\Odluke\OdlukeClient;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class OdlukeDownloadTool extends Tool
{
    protected string $name = 'odluke-download';
    protected string $title = 'Preuzimanje odluke (PDF/HTML)';
    protected string $description = 'Preuzmi odluku (PDF/HTML). Parametri: id (GUID), format {pdf|html|both}, save {true|false}, base_url';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id'       => $schema->string()->description('ID (GUID) odluke')->required(),
            'format'   => $schema->string()->enum(['pdf', 'html', 'both'])->default('pdf')->description('Format preuzimanja'),
            'save'     => $schema->boolean()->default(false)->description('Snimi lokalno (storage/app/odluke)'),
            'base_url' => $schema->string()->description('Custom base URL (default iz config/odluke.php)'),
        ];
    }

    public function handle(array $arguments): ToolResult|\Generator
    {
        $id = (string)($arguments['id'] ?? '');
        if (trim($id) === '') {
            return ToolResult::error('Parametar "id" je obavezan.');
        }

        $format = $arguments['format'] ?? 'pdf';
        if (!in_array($format, ['pdf', 'html', 'both'], true)) {
            $format = 'pdf';
        }
        $save = (bool)($arguments['save'] ?? false);

        $client = OdlukeClient::fromConfig()->withBaseUrl($arguments['base_url'] ?? null);
        $outDir = config('odluke.out_dir') ?: storage_path('app/odluke');
        @is_dir($outDir) || @mkdir($outDir, 0775, true);

        $meta = $client->fetchDecisionMeta($id) ?? [];
        $basename = $client->buildBaseFileName($meta, $id);

        $result = [
            'id' => $id,
            'meta' => $meta,
            'download_pdf_url' => $client->downloadPdfUrl($id),
            'download_html_url' => $client->downloadHtmlUrl($id),
            'saved' => [],
            'errors' => [],
        ];

        if (in_array($format, ['pdf', 'both'], true)) {
            $pdf = $client->downloadPdf($id);
            if ($pdf['ok'] ?? false) {
                if ($save) {
                    $path = $outDir . '/' . $basename . '.pdf';
                    @file_put_contents($path, $pdf['bytes']);
                    $result['saved']['pdf'] = $path;
                } else {
                    $result['pdf'] = [
                        'content_type' => $pdf['content_type'] ?? null,
                        'bytes' => strlen($pdf['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['pdf'] = 'HTTP ' . ($pdf['status'] ?? '??');
            }
        }

        if (in_array($format, ['html', 'both'], true)) {
            $html = $client->downloadHtml($id);
            if ($html['ok'] ?? false) {
                if ($save) {
                    $path = $outDir . '/' . $basename . '.html';
                    @file_put_contents($path, $html['bytes']);
                    $result['saved']['html'] = $path;
                } else {
                    $result['html'] = [
                        'content_type' => $html['content_type'] ?? null,
                        'bytes' => strlen($html['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['html'] = 'HTTP ' . ($html['status'] ?? '??');
            }
        }

        $ok = empty($result['errors']);
        $text = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $ok ? ToolResult::text($text) : ToolResult::error($text);
    }
}
