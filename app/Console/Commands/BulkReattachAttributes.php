<?php

namespace App\Console\Commands;

use App\Services\OpenAIClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BulkReattachAttributes extends Command
{
    protected $signature = 'vs:bulk-reattach
    {--mapping= : Putanja do mapping JSON-a (default: storage/app/tagged/mappping2.json)}
        {--group=* : Filtriraj samo određene grupe (može više puta navesti opciju)}
        {--vs= : Fiksni VS ID (preskače mapiranje po grupi)}
        {--sleep=150 : Pauza između poziva u ms}
        {--dry : Dry-run, ne zovi API}';


    protected $description = 'Masovno re-attach-a atribute fileova u zadanom VS-u na temelju Tagger JSON-ova';

    public array $vsIdMapping = [
        'KP-DO-731' => 'vs_68c89c6bb90081918cf07e4441f58ecc',
        'Pp Prz-74-2025' => 'vs_68c89c6bb90081918cf07e4441f58ecc',
        'Ostalo' => 'vs_68c89c6bb90081918cf07e4441f58ecc',
        'Zakoni' => 'vs_68c89c812d408191add94d47a2b749e8',
    ];

    public function handle(OpenAIClient $client)
    {
        $mappingPath = $this->option('mapping') ?: storage_path('app/tagged/mappping.json');
        if (!file_exists($mappingPath)) {
            $this->error("Ne postoji mapping file: {$mappingPath}");
            return 1;
        }
        $mappingArray = json_decode(file_get_contents($mappingPath), true);
        $mappingArray = collect($mappingArray)->filter(function ($item) {
            return !$item['file_id'] ?? true;
        })->map(function ($item) {
                //$filePath = $item['file_path'] ?? null;
                //$filePathArray = is_string($filePath) ? explode("/", $filePath) : [];
//                $group = $filePathArray[3] ?? null;
//                dd($group);
//                $vsId = $this->vsIdMapping[$group] ?? null;
//                $contains = Str::contains($filePath, 'clanak', true);
//                if ($contains) {
//                    $vsId = 'vs_68c89c812d408191add94d47a2b749e8';
//                }
                $vsId = 'vs_68c89c6bb90081918cf07e4441f58ecc';
                $item['group'] = $vsId;
                return $item;
            })
            ->when($this->option('group'), function ($c) {
                $wanted = $this->option('group') ?: [];
                return $c->filter(fn($it) => in_array($it['group'] ?? null, $wanted, true));
            })
            ->groupBy('group')
            ->toArray();


        $forcedVsId = $this->option('vs') ?: null;
        $sleepMs = (int) $this->option('sleep');
        $dry = (bool) $this->option('dry');

        $wanted = [];
        $count = 0;
        foreach ($mappingArray as $groupIndex => $groupArray) {
            foreach ($groupArray as $item) {
                $fileId = $item['file_id'] ?? null;
                $filePath = $item['file_path'] ?? null;
                $folderPath = pathinfo($filePath, PATHINFO_DIRNAME);
                if (!$fileId) {
                    $filePath = $item['file_path'] ?? null;
                    try {
                        $response = $client->uploadFile($filePath);
                    } catch (\Exception $e) {
                        $this->error($e->getMessage());
                        continue;
                    }

                    $fileId = $response['id'] ?? null;
                    $item['file_id'] = $fileId;
                    $this->info('Upload-ano: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                }

                $metaPath = $item['file_response_metadata'] ?? null;
                if (!$metaPath || !is_file($metaPath)) {
                    $this->warn("Preskačem: nema metadata JSON-a za file_id={$fileId}");
                    continue;
                }
                $fileMetadata = file_get_contents($metaPath);
                $meta = json_decode($fileMetadata, true);
                if (!is_array($meta)) {
                    $this->warn("Preskačem: oštećen metadata JSON za file_id={$fileId}");
                    continue;
                }

                $vsId = $item['group'] ?? null;
                //$vsId = $forcedVsId ?: ($this->vsIdMapping[$groupName] ?? null);
                if (!$vsId) {
                    $this->warn("Nema VS ID mapiranja za group ");
                    continue;
                }

                $meta['law_citations'] = isset($meta['law'][0]['citations'])
                    ? implode(';', array_filter(array_column($meta['law'][0]['citations'], 'clanak')))
                    : null;

                $attributes = $this->compactMetadataAttributes($meta);
                dump(json_encode($attributes, JSON_PRETTY_PRINT));

                if ($dry) {
                    $this->line("[DRY] POST /vector_stores/{$vsId}/files -> ".json_encode(['file_id'=>$fileId,'attributes'=>$attributes], JSON_UNESCAPED_UNICODE));
                } else {
                    try {
                        $response = $client->post("/vector_stores/{$vsId}/files", [
                            'file_id' => $fileId,
                            'attributes' => $attributes,
                        ])->throw();
                    } catch (\Exception $e) {
                        $this->error($e->getMessage());
                    }
                    $this->info("Reattach-ano: ".json_encode($response->json(), JSON_UNESCAPED_UNICODE));
                    //file_put_contents($filePath . "/$filePath.response.json", json_encode($response['file_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                $wanted[] = $item;
                Cache::put("wanted", $wanted, now()->addHours(10));
                $count++;
                usleep(max(0, $sleepMs) * 1000);
            }
        }
        $origMapping = file_get_contents($mappingPath);
        $mappingArray = json_decode($origMapping, true);
        $mappingArray = collect($mappingArray)->filter(function ($item) {
            return !$item['file_id'] ?? true;
        })->toArray();
        $mappingArray = array_merge($mappingArray, $wanted);
        file_put_contents(storage_path('app/tagged/mappping.json'), json_encode($mappingArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Gotovo. Reattach-ano: {$count}");
        return 0;
    }

    /**
     * Flatten a nested array into dot.notation key => string value pairs,
     * normalizing key segments to ASCII-safe characters. Nulls and empty arrays are skipped.
     */
    function compactMetadataAttributes(array $m): array
    {
        // 1) Keywords (handles diacritic and ascii versions)
        $keywordsArr = $m['ključne_riječi'] ?? $m['kljucne_rijeci'] ?? [];
        $keywordsArr = array_map('strval', $keywordsArr);

        // 2) Detect person (from keywords; looks for "Firstname Lastname")
        $person = '';
        foreach ($keywordsArr as $k) {
            if (preg_match('/\b\p{Lu}\p{Ll}+\s+\p{Lu}\p{Ll}+\b/u', $k)) {
                $person = $k;
                break;
            }
        }

        // 3) Detect police unit (from keywords)
        $policeUnit = '';
        foreach ($keywordsArr as $k) {
            if (stripos($k, 'PU ') !== false) {
                $policeUnit = $k;
                break;
            }
        }

        // 4) Order terms (from keywords)
        $orderTermsWanted = ['rok 3 dana', 'pravo na branitelja', 'žalba nije dopuštena'];
        $orderTerms = array_values(array_intersect($orderTermsWanted, $keywordsArr));

        // 5) Law codes + citations summary
        $law = $m['law'] ?? [];
        $codeShort = [];   // map full name => short code (PZ, ZKP, ZSZD) if present
        foreach ($law as $item) {
            $aliases = $item['law_code_alias'] ?? [];
            foreach ($aliases as $alias) {
                if (in_array($alias, ['PZ', 'ZKP', 'ZSZD'], true)) {
                    $codeShort[$item['law_code']] = $alias;
                }
            }
        }

        // Build code list and citations per code
        $codesSeen = [];
        $byCode = [];
        foreach ($law as $item) {
            $code = $item['law_code'] ?? '';
            if (!$code) continue;
            $codesSeen[$code] = true;

            foreach (($item['citations'] ?? []) as $c) {
                $part = $c['clanak'] ?? '';
                $part = "čl. " . $part;
                if (!empty($c['stavci'])) $part .= ' st.' . implode(',', $c['stavci']);
                if (!empty($c['tocke']))  $part .= ' t.' . implode(',', $c['tocke']);
                if ($part !== '') $byCode[$code][] = $part;
            }
        }

        // law_codes (short + full where available)
        $lawCodesParts = [];
        foreach (array_keys($codesSeen) as $full) {
            $short = $codeShort[$full] ?? null;
            $lawCodesParts[] = $short ? "{$short} ({$full})" : $full;
        }
        $lawCodesStr = implode('; ', $lawCodesParts);

        // law_articles (citations grouped by short code if known)
        $lawArticlesParts = [];
        foreach ($byCode as $full => $citations) {
            $label = $codeShort[$full] ?? $full;
            $lawArticlesParts[] = $label . ': ' . implode('; ', $citations);
        }
        $lawArticlesStr = implode('; ', $lawArticlesParts);

        // 6) Related cases
        $relatedCasesStr = implode('; ', array_map('strval', $m['related_cases'] ?? []));

        // 7) Build compact attributes (ASCII keys; values keep original diacritics)
        $attributes = [
            'file_name'      => (string)($m['file_name'] ?? ''),
            'jurisdikcija'   => (string)($m['jurisdikcija'] ?? ''),
            'case_id'        => (string)($m['case_id'] ?? ''),
            'related_cases'  => $relatedCasesStr,
            'vrsta'          => (string)($m['vrsta'] ?? ''),
            'datum'          => (string)($m['datum'] ?? ''),
            'lokacija'       => (string)($m['lokacija'] ?? ''),
            'law_codes'      => $lawCodesStr,
            'law_articles'   => $lawArticlesStr,
            'keywords'       => Str::limit( implode(', ', $keywordsArr), 500),
            'order_terms'    => implode('; ', $orderTerms),
            'person'         => $person,
            'police_unit'    => $policeUnit,
            'izvor'          => (string)($m['izvor'] ?? ''),
            'confidence'     => (string)($m['confidence'] ?? ''),
            // Optional 16th slot for full JSON if you want it:
            // 'raw_metadata' => json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        // Remove empty values to ensure we stay under the limit and keep it clean
        return array_filter($attributes, fn($v) => $v !== '' && $v !== null);
    }
}
