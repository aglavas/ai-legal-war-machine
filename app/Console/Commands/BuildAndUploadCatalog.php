<?php

namespace App\Console\Commands;

use App\Services\OpenAIClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuildAndUploadCatalog extends Command
{
    protected $signature = 'vs:build-catalog
    {--catalog-dir= : Katalog root (default: storage/app/catalog)}
        {--attach : Nakon gradnje odmah upload/attach u VS}
        {--mapping= : Putanja do mapping JSON-a (za legacy dio gradnje iz mapiranja)}
        {--overwrite : Prepiši postojeće catalog.json}
        {--group=* : Filtriraj grupe (kad se gradi iz mappinga)}
        {--dry : Dry-run za attach}';


    protected $description = 'Generira catalog.jsonl iz Tagger JSON-ova i (opcija) attach-a u VS';

    public array $vsIdMapping = [
        'KP-DO-731' => 'vs_68c73e52abd48191aa0d3b07ffbeb0ca',
        'Pp Prz-74-2025' => 'vs_68c73e39e810819184df43182f7e4268',
        'Ostalo' => 'vs_68c749c274548191b81565bb09c529f1',
        'ZAKONI' => 'vs_68c749c9937081919d189d80f263dbcb',
    ];

    public function handle(OpenAIClient $client)
    {
        $catalogPath = $this->option('catalog-dir') ?: storage_path('app/catalog');
        $attach = (bool) $this->option('attach');
        $overwrite = (bool) $this->option('overwrite');
        $dry = (bool) $this->option('dry');

        if (!is_dir($catalogPath)) {
            $this->error("Ne postoji dir: {$catalogPath}");
            return 1;
        }

//        $dirPathArray = scandir($catalogPath);
//
//        foreach ($dirPathArray as $dirPath) {
//            if (in_array($dirPath, ['.', '..'])) {
//                continue;
//            }
//            $isDir = is_dir($catalogPath . '/' . $dirPath);
//            if (!$isDir) {
//                continue;
//            }
//            $outPath = $catalogPath . '/' . $dirPath . '/catalog.json';
//
//            if (!File::exists($outPath)) {
//                $this->warn("Preskačem, nema catalog.json: {$outPath}");
//                continue;
//            }
//
//            if ($attach) {
//                if ($dry) {
//                    $this->line("[DRY] Upload + attach catalog: {$outPath} -> VS {$dirPath}");
//                } else {
//                    $cat = $client->uploadFile($outPath);
//                    $response = $client->post("/vector_stores/{$dirPath}/files", [
//                        'file_id' => $cat['id'],
//                        'attributes' => [
//                            'type' => 'assistants',
//                            'document_type' => 'catalog',
//                        ]
//                    ])->throw();
//                    $this->info("Catalog upload-ano: ".json_encode($response->json(), JSON_UNESCAPED_UNICODE));
//                }
//            }
//        }



        $mappingPath = $this->option('mapping') ?: null;
        if (!$mappingPath) {
            return 0;
        }
        if (!is_file($mappingPath)) {
            $this->error("Ne postoji mapping file: {$mappingPath}");
            return 1;
        }

        $mappingJson = file_get_contents($mappingPath);
        $mappingArray = json_decode($mappingJson, true);

        //dd($mappingArray);

        $lines = [];
        $firstCase = null;


        foreach ($mappingArray as $mappingData) {
            $metadataPath = $mappingData['file_response_metadata'] ?? null;
            $meta = json_decode(file_get_contents($metadataPath), true);
            if (!is_array($meta)) continue;

            $firstCase ??= $meta['case_id'] ?? null;

            $device = $meta['device'] ?? [];
            $imeis  = isset($device['imei']) ? implode(',', $device['imei']) : null;
            $msisdn = isset($device['msisdn']) ? implode(',', $device['msisdn']) : null;

            $law   = $meta['law'] ?? [];
            $pins  = [];
            foreach ($law as $index => $lawSingle) {
                foreach ($lawSingle['citations'] ?? [] as $c) {
                    $lawName = $lawSingle['law_code'] ?? '';
                    $lawNameAlias = isset($lawSingle['law_code_alias']) ? reset($lawSingle['law_code_alias']) : '';
                    if (!empty($c['clanak'])) {
                        $st = isset($c['stavci']) ? implode(',', $c['stavci']) : null;
                        $t = isset($c['tocke']) ? implode(',', $c['tocke']) : null;
                        $pins[] = $lawName . " ({$lawNameAlias}) "  . 'cl.'.$c['clanak'].($st ? ' st.'.$st : '') .($t ? ' t.'.$t : '');
                    }
                }
            }

            $anchors = $meta['anchors'] ?? [];
            $anchorText = implode(' | ', array_map(
                fn($a) => ($a['field'] ?? '').': '.($a['quote'] ?? ''),
                array_slice($anchors, 0, 4)
            ));

            $filePath = $mappingData['file_path'] ?? null;
            $vsIdCheck = Str::contains($filePath, " - clanak-", true);
            if ($vsIdCheck) {
                $vsId = 'vs_68c89c812d408191add94d47a2b749e8';
            } else {
                $vsId = 'vs_68c89c6bb90081918cf07e4441f58ecc';
            }

            $searchText = implode(' | ', array_filter([
                "filename: " . $mappingData['file_name'] ?? '',
                "file_id: " . $mappingData['file_id'] ?? '',
                "vector_store_id: " . $vsId ?? '',
                $meta['case_id'] ?? '',
                $meta['vrsta'] ?? '',
                $meta['artifact'] ?? '',
                $device['tip'] ?? '', $device['marka'] ?? '', $device['model'] ?? '',
                $imeis ? 'IMEI:'.$imeis : null,
                $msisdn ? 'MSISDN:'.$msisdn : null,
                $law['law_code'] ?? '',
                implode(' ', $pins),
                implode(' ', $meta['ključne_riječi'] ?? []),
                implode(' ', $meta['kategorije_povrede'] ?? []),
                $anchorText
            ]));

            $lines[] = [
                'type' => 'catalog_entry',
                'file_name' => $meta['file_name'] ?? null,
                'file_id' => $mappingData['file_id'] ?? null,
                'title' => explode(' - clanak', $meta['file_name'] ?? null)[0],
                'case_id' => $meta['case_id'] ?? null,
                'related_cases' => $meta['related_cases'] ?? [],
                'artifact' => $meta['artifact'] ?? null,
                'device' => $device,
                'law' => $law,
                'vs_id' => $vsId,
                'kategorije_povrede' => $meta['kategorije_povrede'] ?? [],
                'ključne_riječi' => $meta['ključne_riječi'] ?? [],
                'text' => $searchText,
                'anchors' => $anchors,
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $lines = collect($lines)->when($this->option('group'), function ($c) {
            $wanted = $this->option('group') ?: [];
            return $c->filter(fn($it) => in_array($it['vs_id'] ?? null, $wanted, true));
        })->groupBy('vs_id')->toArray();
        dd($lines);

        $outPathArray = [];
        foreach ($lines as $vsId => $cases) {
            $casesJson = json_encode($cases, JSON_UNESCAPED_UNICODE);
            $outPath = rtrim($catalogPath, '/') . "/$vsId/catalog.json";
            $outPathArray[$vsId] = $outPath;
            if (File::exists($outPath) && !$overwrite) {
                $this->warn("Preskačem, već postoji: {$outPath}");
                continue;
            }

            @mkdir(dirname($outPath), 0775, true);
            file_put_contents($outPath, $casesJson);
            $this->info("catalog.json kreiran: {$outPath}");
        }

        if ($attach) {
            foreach ($lines as $vsId => $cases) {
                $caseId = reset($cases)['case_id'] ?? null;
                $outPath = $outPathArray[$vsId] ?? null;
                if (!$outPath || !is_file($outPath)) {
                    $this->warn("Nema izlazne datoteke za attach: {$vsId}");
                    continue;
                }

                if ($dry) {
                    $this->line("[DRY] Upload + attach catalog: {$outPath} -> VS {$vsId}");
                    continue;
                }

                $cat = $client->uploadFile($outPath);
                $response = $client->post("/vector_stores/{$vsId}/files", [
                    'file_id' => $cat['id'],
                    'attributes' => [
                        'type' => 'assistants',
                    ]
                ])->throw();

                $this->info("Catalog upload-ano: ".json_encode($response->json(), JSON_UNESCAPED_UNICODE));
                $this->info("Catalog attach-an u VS: {$vsId}");
            }
        }



        dd('stop');;

        $lines = collect($lines)->groupBy('vs_id')->toArray();

        $outPathArray = [];
        foreach ($lines as $vsId => $cases) {
            $cases = json_encode($cases, JSON_UNESCAPED_UNICODE);
            $outPath = storage_path('app/catalog') . "/$vsId/catalog.json";
            $outPathArray[$vsId] = $outPath;
            if (File::exists($outPath)) {
                $this->warn("Preskačem, već postoji: {$outPath}");
                continue;
            }

            file_put_contents($outPath, $cases);
            $this->info("catalog.json kreiran: {$outPath}");
        }

        foreach ($lines as $vsId => $cases) {
            $caseId = reset($cases)['case_id'] ?? null;
            $outPath = $outPathArray[$vsId] ?? null;
            $cat = $client->uploadFile($outPath);
            $response = $client->post("/vector_stores/{$vsId}/files", [
                'file_id' => $cat['id'],
                'attributes' => [
                    'type' => 'assistants',
                    'case_id' => $caseId,
                ]
            ])->throw();

            $this->info("Catalog upload-ano: ".json_encode($response->json(), JSON_UNESCAPED_UNICODE));
            $this->info("Catalog attach-an u VS: {$vsId}");
        }

//        dd('stop');
//
//
//        // Opcionalno: upload i attach originalnih fileova
//        foreach ($paths as $p) {
//            $meta = json_decode(file_get_contents($p), true);
//            $src = $filesD.'/'.($meta['file_name'] ?? '');
//            if (!is_file($src)) {
//                $this->warn("Preskačem, nema filea: {$src}");
//                continue;
//            }
//            $fo = $client->uploadFile($src);
//            $attrs = [
//                'case_id'  => $meta['case_id'] ?? null,
//                'vrsta'    => $meta['vrsta'] ?? null,
//                'artifact' => $meta['artifact'] ?? null,
//                'device_tip' => $meta['device']['tip'] ?? null,
//                'imei'     => isset($meta['device']['imei']) ? implode(',', $meta['device']['imei']) : null,
//                'msisdn'   => isset($meta['device']['msisdn']) ? implode(',', $meta['device']['msisdn']) : null,
//                'law_code' => $meta['law']['law_code'] ?? null,
//                'law_citations' => isset($meta['law']['citations'])
//                    ? implode(';', array_filter(array_column($meta['law']['citations'], 'clanak')))
//                    : null,
//            ];
//            $client->post("/vector_stores/{$vsId}/files", [
//                'file_id' => $fo['id'],
//                'attributes' => array_filter($attrs, fn($v) => !is_null($v) && $v !== ''),
//            ])->throw();
//            $this->line("Attach: ".$meta['file_name']." -> ".$fo['id']);
//        }

        return 0;
    }
}
