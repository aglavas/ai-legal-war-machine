<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ZakonHrScraper
{
    private const BASE_URL = 'https://zakon.hr';

    private const CATEGORY_URLS = [
        98 => 'https://www.zakon.hr/search.htm?povezani=98',
        99 => 'https://www.zakon.hr/search.htm?povezani=99',
        100 => 'https://www.zakon.hr/search.htm?povezani=100',
        101 => 'https://www.zakon.hr/search.htm?povezani=101',
    ];

    /**
     * Scrape all configured category URLs and return collected laws
     *
     * @return array
     */
    public function scrapeAllCategories(): array
    {
        $allLaws = [];

        foreach (self::CATEGORY_URLS as $categoryId => $url) {
            try {
                $laws = $this->scrapeCategoryPage($url);
                $allLaws[$categoryId] = [
                    'url' => $url,
                    'count' => count($laws),
                    'laws' => $laws,
                ];
            } catch (\Exception $e) {
                Log::error("Failed to scrape category {$categoryId}", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $allLaws[$categoryId] = [
                    'url' => $url,
                    'count' => 0,
                    'laws' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $allLaws;
    }

    /**
     * Scrape a single category page and extract law links
     *
     * @param string $url
     * @return array
     */
    public function scrapeCategoryPage(string $url): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch URL: {$url}. Status: {$response->status()}");
        }

        $html = $response->body();

        return $this->parseHtml($html);
    }

    /**
     * Parse HTML and extract law links
     *
     * @param string $html
     * @return array
     */
    private function parseHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $laws = [];

        // Find the main content div
        $contentDiv = $crawler->filter('div.tekst-zakona.strana-f');

        if ($contentDiv->count() === 0) {
            Log::warning('Could not find content div with class "tekst-zakona strana-f"');
            return [];
        }

        // Extract all links within the content div
        $contentDiv->filter('a')->each(function (Crawler $node) use (&$laws) {
            $href = $node->attr('href');
            $title = $node->attr('title') ?? $node->text();

            // Only collect links that start with /z/ (law links)
            if ($href && str_starts_with($href, '/z/')) {
                // Make absolute URL
                $absoluteUrl = self::BASE_URL . $href;

                // Extract law number from URL (e.g., /z/98/kazneni-zakon -> 98)
                preg_match('/\/z\/(\d+)\//', $href, $matches);
                $lawNumber = $matches[1] ?? null;

                // Extract slug from URL
                preg_match('/\/z\/\d+\/(.+)$/', $href, $slugMatches);
                $slug = $slugMatches[1] ?? null;

                $laws[] = [
                    'title' => trim($title),
                    'url' => $absoluteUrl,
                    'relative_url' => $href,
                    'law_number' => $lawNumber,
                    'slug' => $slug,
                ];
            }
        });

        // Remove duplicates based on URL
        $uniqueLaws = [];
        $seenUrls = [];

        foreach ($laws as $law) {
            if (!in_array($law['url'], $seenUrls)) {
                $uniqueLaws[] = $law;
                $seenUrls[] = $law['url'];
            }
        }

        return $uniqueLaws;
    }

    /**
     * Get all unique laws from all categories
     *
     * @return array
     */
    public function getUniqueLaws(): array
    {
        $categoriesData = $this->scrapeAllCategories();
        $allLaws = [];
        $seenUrls = [];

        foreach ($categoriesData as $categoryId => $data) {
            foreach ($data['laws'] ?? [] as $law) {
                if (!in_array($law['url'], $seenUrls)) {
                    $law['found_in_categories'] = [$categoryId];
                    $allLaws[$law['url']] = $law;
                    $seenUrls[] = $law['url'];
                } else {
                    // Law already exists, just add this category to the list
                    $allLaws[$law['url']]['found_in_categories'][] = $categoryId;
                }
            }
        }

        return array_values($allLaws);
    }

    /**
     * Get statistics about scraped laws
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $categoriesData = $this->scrapeAllCategories();
        $totalLaws = 0;
        $totalUnique = 0;
        $seenUrls = [];

        foreach ($categoriesData as $data) {
            $totalLaws += $data['count'];
            foreach ($data['laws'] ?? [] as $law) {
                if (!in_array($law['url'], $seenUrls)) {
                    $totalUnique++;
                    $seenUrls[] = $law['url'];
                }
            }
        }

        return [
            'total_laws' => $totalLaws,
            'unique_laws' => $totalUnique,
            'categories_scraped' => count($categoriesData),
            'categories' => $categoriesData,
        ];
    }
}
