<?php

namespace App\Services;

class MetadataBuilder
{
    public function buildArticleMetadata(array $context): array
    {
        // $context sadrži iz LawFetcher i LawParser, npr. year, edition, act, eli_resource, eli_expression, etc.
        $id = sprintf(
            'urn:hr-law:%s#clanak-%s',
            trim(parse_url($context['eli_resource'], PHP_URL_PATH), '/'),
            $context['article_number']
        );

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'id' => $id,
            'identifier' => $id,
            'inLanguage' => 'hr',
            'name' => sprintf('%s – Članak %s', $context['title'], $context['article_number']),
            'datePublished' => $context['date_publication'],
            'isBasedOn' => [
                '@type' => 'Legislation',
                'identifier' => $context['eli_resource'],
                'sameAs' => [
                    $context['eli_expression'],
                    $context['html_url'],
                    $context['pdf_url'],
                ]
            ],
            'about' => [
                'type_document' => $context['type_document'],
                'nn_part' => 'SL',
                'nn_year' => $context['year'],
                'nn_edition' => $context['edition'],
                'nn_act' => $context['act'],
                'is_consolidated_text' => $context['is_consolidated'] ?? false,
            ],
            'article' => [
                'number' => (string)$context['article_number'],
                'heading_chain' => $context['heading_chain'] ?? [],
                'text_checksum' => $context['text_checksum'] ?? null,
            ],
            'file' => [
                'path' => $context['file_path'] ?? null,
                'bytes' => $context['file_bytes'] ?? null,
                'sha256' => $context['file_sha256'] ?? null,
            ],
            'generator' => [
                'name' => 'hr-law-ingestor',
                'version' => $context['generator_version'] ?? '1.0.0',
                'generated_at' => $context['generated_at'] ?? gmdate('c'),
            ],
        ];
    }
}
