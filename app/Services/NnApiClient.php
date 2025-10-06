<?php

namespace App\Services;

use GuzzleHttp\Client;

class NnApiClient
{
    private Client $http;
    private string $base = 'https://narodne-novine.nn.hr';

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'base_uri' => $this->base,
            'timeout'  => 20,
            'headers'  => [
                'Accept' => 'application/json',
                'User-Agent' => 'hr-law-ingestor/1.0 (+contact@example.com)'
            ],
        ]);
    }

    public function years(): array
    {
        $res = $this->http->get('/api/index');
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }

    public function editions(int $year, string $part = 'SL'): array
    {
        $res = $this->http->post('/api/editions', [
            'json' => ['part' => $part, 'year' => $year]
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }

    public function acts(int $year, int $edition, string $part = 'SL'): array
    {
        $res = $this->http->post('/api/acts', [
            'json' => ['part' => $part, 'year' => $year, 'number' => $edition]
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }

    public function actJsonLd(int $year, int $edition, string $actNum, string $part = 'SL'): array
    {
        $res = $this->http->post('/api/act', [
            'json' => [
                'part'    => $part,
                'year'    => $year,
                'number'  => $edition,
                'act_num' => $actNum,
                'format'  => 'JSON-LD'
            ]
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    }

    public static function extractUrlsFromJsonLd(array $jsonLd): array
    {
        // Vrati ELI Resource, Expression i linkove na html/printhtml/pdf iz JSON-LD grafa
        $resource = null; $expression = null; $html = null; $printhtml = null; $pdf = null; $title = null; $language = null; $datePub = null; $typeDoc = null;

        foreach ($jsonLd as $node) {
            if (!isset($node['@id'])) continue;
            $id = $node['@id'];

            // LegalResource čvor
            if (isset($node['@type']) && in_array('http://data.europa.eu/eli/ontology#LegalResource', (array)$node['@type'])) {
                $resource = $id;
                $datePub = $node['http://data.europa.eu/eli/ontology#date_publication'][0]['@value'] ?? null;
                $typeDoc = $node['http://data.europa.eu/eli/ontology#type_document'][0]['@id'] ?? null;
            }

            // LegalExpression čvor
            if (isset($node['@type']) && in_array('http://data.europa.eu/eli/ontology#LegalExpression', (array)$node['@type'])) {
                $expression = $id;
                $title = $node['http://data.europa.eu/eli/ontology#title'][0]['@value'] ?? null;
                $language = $node['http://data.europa.eu/eli/ontology#language'][0]['@id'] ?? null;
                $embodies = $node['http://data.europa.eu/eli/ontology#is_embodied_by'] ?? [];
                foreach ($embodies as $emb) {
                    $fmtId = $emb['@id'] ?? null;
                    if (!$fmtId) continue;
                    if (str_ends_with($fmtId, '/html')) $html = $fmtId;
                    if (str_ends_with($fmtId, '/printhtml')) $printhtml = $fmtId;
                    if (str_ends_with($fmtId, '/pdf')) $pdf = $fmtId;
                }
            }
        }

        return [
            'eli_resource' => $resource,
            'eli_expression' => $expression,
            'html' => $html,
            'printhtml' => $printhtml,
            'pdf' => $pdf,
            'title' => $title,
            'language' => $language,
            'date_publication' => $datePub,
            'type_document' => $typeDoc,
        ];
    }
}
