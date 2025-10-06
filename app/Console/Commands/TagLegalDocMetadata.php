<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TagLegalDocMetadata extends Command
{
    protected $signature = 'ai:tag
        {file* : Putanja(e) do datoteke ili direktorija (PDF/DOCX/TXT...)}
        {--model=gpt-5 : OpenAI model}
        {--store : Postavi store=true kako bi se spremio response na OpenAI strani}
        {--timeout=180 : HTTP timeout u sekundama}
        {--base-url= : Override za OpenAI base URL}
        {--out= : Izlazni direktorij (default: storage/app/tagged)}
        {--ext=pdf,docx,txt : Ekstenzije koje se procesiraju kada je argument direktorij (comma-separated)}
        {--dry : Dry-run, ne šalji zahtjeve}';


    protected $description = 'Uploada datoteku na OpenAI i traži ekstrakciju metapodataka (tag_file_metadata) uz Responses API + file_search.';

    public function handle(): int
    {
        $outDir = $this->option('out') ?: storage_path('app/tagged');
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $exts = array_filter(array_map('strtolower', array_map('trim', explode(',', (string)$this->option('ext')))));
        $paths = (array)$this->argument('file');
        $targets = $this->expandTargets($paths, $exts);

        if (empty($targets)) {
            $this->warn('Nema datoteka za obradu.');
            return self::SUCCESS;
        }

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (!$apiKey) {
            $this->error('Nedostaje OPENAI_API_KEY u .env / config/services.php');
            return self::FAILURE;
        }

        $dry = (bool)$this->option('dry');
        $model = (string)$this->option('model');
        $store = (bool)$this->option('store');
        $timeout = (int)$this->option('timeout');
        $baseUrl = rtrim($this->option('base-url') ?: env('OPENAI_BASE_URL', 'https://api.openai.com'), '/');

        $mappingFile = $outDir . '/mappping.json';
        $mappingArray = [];
        if (is_file($mappingFile)) {
            $mappingArray = json_decode(file_get_contents($mappingFile), true) ?: [];
        }

        foreach ($targets as $filePath) {
            $res = $this->processOne($filePath, $apiKey, $baseUrl, $model, $timeout, $store, $outDir, $dry);
            if ($res['ok']) {
                $mappingArray[] = $res['mapping'];
                file_put_contents($mappingFile, json_encode($mappingArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("Ažuriran je mappping.json s novim unosom za: " . basename($filePath));
            } else {
                $this->warn("Preskočeno/greška za: {$filePath} -> {$res['error']}");
            }
        }

        return self::SUCCESS;
    }

    private function expandTargets(array $inputs, array $exts): array
    {
        $files = [];
        foreach ($inputs as $path) {
            if (is_dir($path)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($iter as $fileInfo) {
                    if (!$fileInfo->isFile()) continue;
                    $ext = strtolower($fileInfo->getExtension());
                    if ($exts && !in_array($ext, $exts, true)) continue;
                    $files[] = $fileInfo->getPathname();
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }
        // uniq + prirodni poredak
        $files = array_values(array_unique($files));
        natsort($files);
        return array_values($files);
    }

    private function processOne(string $filePath, string $apiKey, string $baseUrl, string $model, int $timeout, bool $store, string $outDir, bool $dry): array
    {
        if (! file_exists($filePath)) {
            return ['ok' => false, 'error' => 'Datoteka ne postoji', 'mapping' => []];
        }

        if ($dry) {
            $this->line("[DRY] Upload + Responses za: {$filePath}");
            $fakeId = 'file_' . substr(hash('sha256', $filePath), 0, 12);
            $metaOutFile = $outDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.metadata.json';
            @file_put_contents($metaOutFile, json_encode(['file_name' => basename($filePath)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return [
                'ok' => true,
                'mapping' => [
                    'file_path' => $filePath,
                    'file_id' => $fakeId,
                    'file_name' => basename($filePath),
                    'file_response' => null,
                    'file_response_metadata' => $metaOutFile,
                ]
            ];
        }

        try {
            $this->info('1/2 Upload datoteke na OpenAI Files API ...');

            $uploadResponse = Http::asMultipart()
                ->withToken($apiKey)
                ->retry(3, 1000)
                ->timeout($timeout)
                ->attach('file', fopen($filePath, 'r'), basename($filePath))
                ->post("{$baseUrl}/v1/files", [
                    'purpose' => 'assistants',
                ]);

            if (! $uploadResponse->successful()) {
                return ['ok' => false, 'error' => 'Greška pri uploadu: ' . $uploadResponse->body(), 'mapping' => []];
            }

            $fileId = $uploadResponse->json('id');
            if (! $fileId) {
                return ['ok' => false, 'error' => 'Nema file_id iz OpenAI Files', 'mapping' => []];
            }

            $this->info("Upload OK. file_id={$fileId}");
            $this->info('2/2 Poziv OpenAI Responses API s priloženom datotekom (attachments + file_search) ...');

            $idempotencyKey = (string) Str::uuid();

            $body = [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'developer',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' =>
                                    'Uloga: “Tagger”. Iz teksta HR pravnog dokumenta izvuci točne metapodatke za File Search / Vector Store. ' .
                                    'Ne nagađaj; ako nema podatka, vrati null. Normaliziraj pojmove i citate. Posebno prepoznaj: case_id (npr. Pp-2343/2025), ' .
                                    'vrstu dokumenta, artefakt (mobitel/laptop/USB…), identifikatore uređaja (IMEI/IMSI/ICCID/MSISDN/serijski), ' .
                                    'pravni izvor (ZKP RH, Ustav RH, EKLJP), članke/stavke/točke, datum i lokaciju radnje, kategorije povreda ' .
                                    '(Formalni elementi; Posebnost; Osnovanost; Hitnost / vremenska opravdanost; Cilj pretresa; Zakonitost postupanja), ' .
                                    'ključne riječi. Uključi “ankere” (kratke navode) koji dokazuju svako detektirano polje. Izlaz je strogo JSON prema shemi funkcije.'
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_file',
                                'file_id' => $fileId
                            ],
                            [
                                'type' => 'input_text',
                                'text' =>
                                    'U prilogu je dokument. Pročitaj ga koristeći file_search i vrati striktno JSON argumente za funkciju tag_file_metadata. ' .
                                    'Ako nema podatka za polje, stavi null (ne nagađaj). ' .
                                    'Naziv datoteke: ' . basename($filePath),
                            ],
                        ],
                    ],
                ],

                'text' => [
                    'format' => ['type' => 'text'],
                    'verbosity' => 'high',
                ],

                'reasoning' => [
                    'effort' => 'high',
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'name' => 'tag_file_metadata',
                        'parameters' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'file_name' => ['type' => 'string'],
                                'jurisdikcija' => [
                                    'type' => 'string',
                                    'enum' => ['HR'],
                                ],
                                'case_id' => [
                                    'anyOf' => [
                                        ['type' => 'string'],
                                        ['type' => 'null'],
                                    ],
                                ],
                                'related_cases' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'vrsta' => [
                                    'type' => 'string',
                                    'enum' => [
                                        'naredba_za_pretragu',
                                        'zapisnik_o_pretrazi',
                                        'zapisnik_o_oduzimanju_predmeta',
                                        'potvrda_o_privremenom_oduzimanju_predmeta',
                                        'forenzičko_izvješće',
                                        'chain_of_custody',
                                        'račun_kupnja',
                                        'dopuna',
                                        'presuda',
                                        'zakon',
                                        'ustav',
                                        'podzakonski_akt',
                                        'drugo',
                                    ],
                                ],
                                'artifact' => [
                                    'type' => 'string',
                                    'enum' => ['mobitel', 'laptop', 'USB', 'HDD', 'SD_kartica', 'drukčiji', 'none'],
                                    'default' => 'none',
                                ],
                                'datum' => [
                                    'anyOf' => [
                                        ['type' => 'string', 'format' => 'date-time'],
                                        ['type' => 'string', 'format' => 'date'],
                                        ['type' => 'null'],
                                    ],
                                ],
                                'lokacija' => [
                                    'anyOf' => [
                                        ['type' => 'string'],
                                        ['type' => 'null'],
                                    ],
                                ],
                                'law' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'properties' => [
                                            'law_code' => [
                                                'anyOf' => [
                                                    ['type' => 'string'],
                                                    ['type' => 'null'],
                                                ],
                                            ],
                                            'law_code_alias' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string'],
                                            ],
                                            'citations' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'additionalProperties' => false,
                                                    'properties' => [
                                                        'clanak' => ['type' => 'string'],
                                                        'stavci' => [
                                                            'type' => 'array',
                                                            'items' => ['type' => 'string'],
                                                        ],
                                                        'tocke' => [
                                                            'type' => 'array',
                                                            'items' => ['type' => 'string'],
                                                        ],
                                                    ],
                                                    'required' => ['clanak', 'stavci', 'tocke'],
                                                ],
                                            ],
                                            'verzija_od' => [
                                                'anyOf' => [
                                                    ['type' => 'string', 'format' => 'date'],
                                                    ['type' => 'null'],
                                                ],
                                            ],
                                            'verzija_do' => [
                                                'anyOf' => [
                                                    ['type' => 'string', 'format' => 'date'],
                                                    ['type' => 'null'],
                                                ],
                                            ],
                                        ],
                                        'required' => ['law_code', 'law_code_alias', 'citations', 'verzija_od', 'verzija_do'],
                                    ],
                                ],
                                'kategorije_povrede' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                        'enum' => [
                                            'Formalni elementi',
                                            'Posebnost',
                                            'Osnovanost',
                                            'Hitnost / vremenska opravdanost',
                                            'Cilj pretresa',
                                            'Zakonitost postupanja',
                                        ],
                                    ],
                                ],
                                'ključne_riječi' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'izvor' => [
                                    'type' => 'string',
                                    'enum' => ['službeni', 'interni', 'neslužbeni'],
                                    'default' => 'interni',
                                ],
                                'store_hint' => [
                                    'anyOf' => [
                                        ['type' => 'string'],
                                        ['type' => 'null'],
                                    ],
                                ],
                                'anchors' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'properties' => [
                                            'field'  => ['type' => 'string'],
                                            'quote'  => ['type' => 'string'],
                                        ],
                                        'required' => ['field', 'quote'],
                                    ],
                                ],
                                'confidence' => [
                                    'type' => 'number',
                                    'minimum' => 0,
                                    'maximum' => 1,
                                ],
                            ],
                            'required' => [
                                'file_name',
                                'jurisdikcija',
                                'case_id',
                                'related_cases',
                                'vrsta',
                                'artifact',
                                'datum',
                                'lokacija',
                                'law',
                                'kategorije_povrede',
                                'ključne_riječi',
                                'izvor',
                                'store_hint',
                                'anchors',
                                'confidence',
                            ],
                        ],
                        'strict' => true,
                    ],
                ],
                'store' => $store,
                'include' => [
                    'reasoning.encrypted_content',
                    'web_search_call.action.sources',
                ],
            ];

            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'OpenAI-Idempotency-Key' => $idempotencyKey,
                ])
                ->retry(3, 1000)
                ->timeout($timeout)
                ->post("{$baseUrl}/v1/responses", $body);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => 'Greška pri Responses API: ' . $response->body(), 'mapping' => []];
            }

            $json = $response->json();

            $outFile = $outDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.response.json';
            file_put_contents($outFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Gotovo. Puni odgovor zapisan u: {$outFile}");

            $arguments = $this->findFunctionArguments($json, 'tag_file_metadata');
            if ($arguments) {
                $this->line('Detektirani argumenti za tag_file_metadata:');
                $this->line(json_encode($arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $metaOutFile = $outDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.metadata.json';
                file_put_contents($metaOutFile, json_encode($arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("Ekstrahirani metapodaci zapisani u: {$metaOutFile}");
            } else {
                $this->warn('Nisam našao jasne argumente funkcijskog poziva u odgovoru. Pogledaj puni JSON.');
                $metaOutFile = null;
            }

            return [
                'ok' => true,
                'mapping' => [
                    'file_path' => $filePath,
                    'file_id' => $fileId,
                    'file_name' => basename($filePath),
                    'file_response' => $outFile,
                    'file_response_metadata' => $metaOutFile,
                ],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'mapping' => []];
        }
    }

    /**
     * Najčešće, Responses API kod function-calla vrati argumente negdje u outputu.
     * Ovo je "best-effort" tražilica po nekoliko tipičnih putanja.
     */
    protected function findFunctionArguments(array $json, string $functionName): ?array
    {
        // Različite verzije mogu imati različitu strukturu. Pokušaj više putanja.
        $candidates = [
            'output.0.arguments', // npr. kad je sadržaj direktno u prvom outputu
            'output.1.arguments', // npr. kad je sadržaj direktno u prvom outputu
            'response.output.0.content.1.arguments',
        ];

        foreach ($candidates as $path) {
            $value = data_get($json, $path);
            if (is_string($value)) {
                // Ponekad dođe kao JSON string
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }
            if (is_array($value)) {
                // Ako postoji i name, provjeri poklapa li se funkcija
                $name = data_get($json, str_replace('arguments', 'name', $path));
                if (! $name || $name === $functionName) {
                    return $value;
                }
            }
        }
        return null;
    }
}
