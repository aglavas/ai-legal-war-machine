<?php

namespace App\Services;

use Illuminate\Support\Str;

class LawFetcher
{
    public function __construct(private NnApiClient $api)
    {
    }

    public function latestConsolidations(int $sinceYear = null): \Generator
    {
        $years = $this->api->years(); // npr. [2015..2025]
        rsort($years);

        foreach ($years as $year) {
            if ($sinceYear && $year < $sinceYear) break;
            $editions = $this->api->editions($year, 'SL');
            rsort($editions);

            foreach ($editions as $edition) {
                $acts = $this->api->acts($year, $edition, 'SL');

                foreach ($acts as $actNum) {
                    // Respect rate limit 3 r/s – kratki sleep ako treba
                    usleep(350000);

                    $jsonLd = $this->api->actJsonLd($year, $edition, (string)$actNum, 'SL');
                    $info = NnApiClient::extractUrlsFromJsonLd($jsonLd);

                    // Heuristika za “pročišćeni tekst”
                    $title = $info['title'] ?? '';
                    $isConsolidated = Str::contains(Str::lower($title), 'pročišćeni tekst') || Str::contains(Str::lower($title), '(pročišćeni tekst)');

                    yield [
                        'year'          => $year,
                        'edition'       => $edition,
                        'act'           => (string)$actNum,
                        'eli_resource'  => $info['eli_resource'],
                        'eli_expression'=> $info['eli_expression'],
                        'title'         => $title,
                        'date_publication' => $info['date_publication'],
                        'html_url'      => $info['printhtml'] ?: $info['html'],
                        'pdf_url'       => $info['pdf'],
                        'language'      => $info['language'],
                        'type_document' => $info['type_document'],
                        'is_consolidated'=> $isConsolidated,
                    ];
                }
            }
        }
    }
}
