<?php

namespace App\Mcp\Tools;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Mcp\Support\PathMapper;

class DownloadOdlukeTool
{
    public const NAME = 'download_odluke';

    public static function definition(): array
    {
        return [
            'name'        => self::NAME,
            'title'       => 'Preuzimanje sudskih odluka (odluke.sudovi.hr)',
            'description' => 'Pokreće Artisan komandu odluke:download i vraća strukturirani izvještaj (stavke po ID‑u, meta, putanje/URL‑ovi).',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'q'        => ['type' => 'string',  'description' => 'Slobodni upit', 'maxLength' => 500],
                    'limit'    => ['type' => 'integer', 'description' => 'Maksimalan broj odluka', 'minimum' => 1, 'maximum' => 5000, 'default' => 50],
                    'format'   => ['type' => 'string',  'enum' => ['pdf','html','both'], 'default' => 'pdf'],
                    'out'      => ['type' => 'string',  'description' => 'Direktorij za spremanje (default: storage/app/odluke)'],
                    'delay'    => ['type' => 'integer', 'description' => 'Pauza u ms', 'minimum' => 0, 'maximum' => 120000],
                    'resume'   => ['type' => 'boolean', 'description' => 'Preskoči već preuzete'],
                    'ids'      => [
                        'description' => 'Popis ID‑eva (GUID) kao array ili zarezom odvojen string',
                        'oneOf'       => [
                            ['type' => 'array', 'items' => ['type' => 'string', 'pattern' => '^[0-9a-fA-F-]{8,}$']],
                            ['type' => 'string']
                        ]
                    ],
                    'params'   => ['type' => 'string',  'description' => 'Dodatni query string', 'maxLength' => 2000],
                    'since'    => ['type' => 'string',  'description' => 'Datum OD (YYYY-MM-DD)', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    'until'    => ['type' => 'string',  'description' => 'Datum DO (YYYY-MM-DD)', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    'dry_run'  => ['type' => 'boolean', 'description' => 'Samo simulacija (bez datoteka)'],
                ],
                'additionalProperties' => false,
            ],
            'outputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'summary' => [
                        'type'       => 'object',
                        'properties' => [
                            'found'        => ['type' => 'integer'],
                            'processed'    => ['type' => 'integer'],
                            'created'      => ['type' => 'integer'],
                            'pdf_saved'    => ['type' => 'integer'],
                            'html_saved'   => ['type' => 'integer'],
                            'duration_ms'  => ['type' => 'integer'],
                            'out_dir'      => ['type' => 'string'],
                            'format'       => ['type' => 'string'],
                        ],
                        'required' => ['created','duration_ms','out_dir','format']
                    ],
                    'items' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'             => ['type' => 'string'],
                                'broj_odluke'    => ['type' => 'string', 'nullable' => true],
                                'sud'            => ['type' => 'string', 'nullable' => true],
                                'datum_odluke'   => ['type' => 'string', 'nullable' => true],
                                'pravomocnost'   => ['type' => 'string', 'nullable' => true],
                                'ecli'           => ['type' => 'string', 'nullable' => true],
                                'src'            => ['type' => 'string', 'nullable' => true],
                                'files' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'pdf'  => ['type' => 'object', 'nullable' => true, 'properties' => [
                                            'path' => ['type' => 'string'], 'url' => ['type' => 'string'], 'size' => ['type' => 'integer']
                                        ]],
                                        'html' => ['type' => 'object', 'nullable' => true, 'properties' => [
                                            'path' => ['type' => 'string'], 'url' => ['type' => 'string'], 'size' => ['type' => 'integer']
                                        ]],
                                        'json' => ['type' => 'object', 'nullable' => true, 'properties' => [
                                            'path' => ['type' => 'string'], 'url' => ['type' => 'string'], 'size' => ['type' => 'integer']
                                        ]],
                                    ],
                                ],
                            ],
                            'required' => ['id','files']
                        ]
                    ],
                    'stdout' => ['type' => 'string'],
                ],
                'required' => ['summary']
            ],
        ];
    }

    public static function call(array $args): array
    {
        $v = Validator::make($args, [
            'q'        => 'sometimes|string|max:500',
            'limit'    => 'sometimes|integer|min:1|max:5000',
            'format'   => 'sometimes|in:pdf,html,both',
            'out'      => 'sometimes|string',
            'delay'    => 'sometimes|integer|min:0|max:120000',
            'resume'   => 'sometimes|boolean',
            'ids'      => 'sometimes',
            'params'   => 'sometimes|string|max:2000',
            'since'    => 'sometimes|date_format:Y-m-d',
            'until'    => 'sometimes|date_format:Y-m-d',
            'dry_run'  => 'sometimes|boolean',
        ]);
        if ($v->fails()) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => "Neispravni parametri:\n" . $v->errors()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
                ],
                'isError' => true,
            ];
        }

        $opts = [];
        $map = [
            'q'      => '--q',
            'limit'  => '--limit',
            'format' => '--format',
            'out'    => '--out',
            'delay'  => '--delay',
            'params' => '--params',
            'since'  => '--since',
            'until'  => '--until',
        ];
        foreach ($map as $key => $cli) {
            if (array_key_exists($key, $args) && $args[$key] !== null && $args[$key] !== '') {
                $opts[$cli] = $args[$key];
            }
        }
        if (array_key_exists('ids', $args) && $args['ids'] !== null && $args['ids'] !== '') {
            $ids = $args['ids'];
            if (is_array($ids)) {
                $ids = implode(',', array_values(array_filter(array_map('trim', $ids), fn($x) => $x !== '')));
            }
            $opts['--ids'] = $ids;
        }
        if (!empty($args['resume']))  $opts['--resume']  = true;
        if (!empty($args['dry_run'])) $opts['--dry-run'] = true;

        if (!isset($opts['--limit']))  $opts['--limit']  = 50;
        if (!isset($opts['--format'])) $opts['--format'] = 'pdf';

        $outDir = $args['out'] ?? storage_path('app/odluke');

        // Snapshot prije
        $before = self::indexJsonMeta($outDir);

        $t0 = (int)(microtime(true) * 1000);
        if (function_exists('set_time_limit')) @set_time_limit(0);

        Artisan::call('odluke:download', $opts);
        $stdout = Artisan::output();

        $t1 = (int)(microtime(true) * 1000);

        // Snapshot poslije
        $after   = self::indexJsonMeta($outDir);
        $newIds  = array_diff(array_keys($after), array_keys($before));
        $created = count($newIds);

        $items = [];
        $pdfSaved = 0; $htmlSaved = 0;

        foreach ($newIds as $id) {
            $meta    = $after[$id]['meta'] ?? [];
            $jsonAbs = $after[$id]['json'] ?? null;

            // NOVO: prvo probaj "sibling" od JSON‑a (foo.json -> foo.pdf/foo.html), zatim fallback po ID‑u
            $pdfAbs  = self::deriveSibling($jsonAbs, 'pdf')  ?? self::findByIdAndExt($outDir, $id, 'pdf');
            $htmlAbs = self::deriveSibling($jsonAbs, 'html') ?? self::findByIdAndExt($outDir, $id, 'html');

            if ($pdfAbs && is_file($pdfAbs))  $pdfSaved++;
            if ($htmlAbs && is_file($htmlAbs)) $htmlSaved++;

            $items[] = [
                'id'           => $id,
                'broj_odluke'  => $meta['broj_odluke']   ?? null,
                'sud'          => $meta['sud']           ?? null,
                'datum_odluke' => $meta['datum_odluke']  ?? null,
                'pravomocnost' => $meta['pravomocnost']  ?? null,
                'ecli'         => $meta['ecli']          ?? null,
                'src'          => $meta['src']           ?? null,
                'files'        => [
                    'pdf'  => PathMapper::fileRef($pdfAbs),
                    'html' => PathMapper::fileRef($htmlAbs),
                    'json' => PathMapper::fileRef($jsonAbs),
                ],
            ];
        }

        $found     = self::rx($stdout, '~Nađeno\s+(\d+)\s+ID~u');
        $processed = self::rx($stdout, '~Gotovo\.\s*Preuzeto\s*\/\s*obrađeno:\s*(\d+)~u');

        $summary = [
            'found'       => $found ? (int)$found : null,
            'processed'   => $processed ? (int)$processed : null,
            'created'     => $created,
            'pdf_saved'   => $pdfSaved,
            'html_saved'  => $htmlSaved,
            'duration_ms' => ($t1 - $t0),
            'out_dir'     => $outDir,
            'format'      => $args['format'] ?? 'pdf',
        ];

        $textSummary = "Sažetak preuzimanja:\n"
            . " - Novo kreirano: {$created}\n"
            . " - PDF spremljeno: {$pdfSaved}\n"
            . " - HTML spremljeno: {$htmlSaved}\n"
            . ($found ? " - Nađeno ID‑eva: {$found}\n" : '')
            . ($processed ? " - Obrađeno: {$processed}\n" : '')
            . " - Trajanje: " . ($t1 - $t0) . " ms\n"
            . " - Direktori: {$outDir}\n";

        $content = [
            ['type' => 'text', 'text' => $textSummary],
        ];

        // Linkovi prema stvarnim nazivima datoteka (basename), prioritet PDF/HTML
        $linkCount = 0;
        foreach ($items as $it) {
            foreach (['pdf','html','json'] as $k) {
                $ref = $it['files'][$k] ?? null;
                if (!empty($ref['url']) && !empty($ref['path'])) {
                    $content[] = [
                        'type'        => 'resource_link',
                        'uri'         => $ref['url'],
                        'name'        => basename($ref['path']),
                        'description' => strtoupper($k) . " za " . ($it['broj_odluke'] ?? $it['id']),
                        'mimeType'    => match ($k) {
                            'pdf'  => 'application/pdf',
                            'html' => 'text/html',
                            'json' => 'application/json',
                            default => 'application/octet-stream'
                        },
                    ];
                    $linkCount++;
                    if ($linkCount >= 6) break 2;
                }
            }
        }

        return [
            'content' => $content,
            'structuredContent' => [
                'summary' => $summary,
                'items'   => $items,
                'stdout'  => $stdout,
            ],
            'isError' => false,
        ];
    }

    // --- helpers ---

    private static function indexJsonMeta(string $outDir): array
    {
        $out = [];
        if (!is_dir($outDir)) return $out;
        $glob = glob(rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($glob as $f) {
            $j = @json_decode(@file_get_contents($f), true);
            if (is_array($j) && !empty($j['id'])) {
                $out[(string)$j['id']] = ['meta' => $j, 'json' => $f];
            }
        }
        return $out;
    }

    private static function findByIdAndExt(string $dir, string $id, string $ext): ?string
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $candidates = array_merge(
            glob("$dir/*{$id}*.{$ext}") ?: [],
            glob("$dir/*{$id}.{$ext}") ?: []
        );
        return $candidates ? $candidates[0] : null;
    }

    // NOVO: iz foo.json izvedi foo.pdf ili foo.html
    private static function deriveSibling(?string $jsonAbs, string $ext): ?string
    {
        if (!$jsonAbs) return null;
        $base = preg_replace('/\.[^.]+$/', '', $jsonAbs);
        if (!$base) return null;
        $candidate = $base . '.' . $ext;
        return is_file($candidate) ? $candidate : null;
    }

    private static function rx(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $m) ? $m[1] : null;
    }
}
