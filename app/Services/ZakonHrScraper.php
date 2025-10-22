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

    /**
     * Scrape the full content of a specific law page
     *
     * @param string $url The law URL to scrape
     * @return array
     */
    public function scrapeLawContent(string $url): array
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch law content from: {$url}. Status: {$response->status()}");
        }

        $html = $response->body();
        $crawler = new Crawler($html);

        $content = [];
        $metadata = [];

        // Extract the main law title
        $titleNode = $crawler->filter('h1, h2.naslov-zakona, .naslov-zakona');
        $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : '';

        // Extract law metadata (number, date, etc.)
        $metaNode = $crawler->filter('.meta-zakon, .podaci-zakon, .metadata');
        if ($metaNode->count() > 0) {
            $metadata['raw_meta'] = trim($metaNode->first()->text());
        }

        // Extract the main law text content
        // Try multiple selectors as the structure might vary
        $contentSelectors = [
            'div.tekst-zakona',
            'div.law-content',
            'div.content',
            'article',
            'div.main-content',
        ];

        $lawText = '';
        foreach ($contentSelectors as $selector) {
            $contentNode = $crawler->filter($selector);
            if ($contentNode->count() > 0) {
                // Get the HTML content and clean it up
                $lawText = $contentNode->first()->html();
                break;
            }
        }

        // If no specific content div found, try to get all paragraph text
        if (empty($lawText)) {
            $crawler->filter('p')->each(function (Crawler $node) use (&$lawText) {
                $lawText .= $node->text() . "\n\n";
            });
        }

        // Extract articles/sections if present
        $articles = [];
        $crawler->filter('.clanak, article.law-article, div[id^="cl"], div[class*="article"]')->each(function (Crawler $node, $i) use (&$articles) {
            $articleTitle = '';
            $articleContent = '';

            // Try to find article title
            $titleNode = $node->filter('h3, h4, strong, .article-title');
            if ($titleNode->count() > 0) {
                $articleTitle = trim($titleNode->first()->text());
            }

            // Get article content
            $articleContent = trim($node->text());

            if (!empty($articleContent)) {
                $articles[] = [
                    'index' => $i,
                    'title' => $articleTitle,
                    'content' => $articleContent,
                ];
            }
        });

        // Clean up the law text
        $lawText = strip_tags($lawText, '<p><br><strong><em><ul><li><ol><h1><h2><h3><h4>');
        $lawText = preg_replace('/\s+/', ' ', $lawText);
        $lawText = trim($lawText);

        return [
            'url' => $url,
            'title' => $title,
            'content' => $lawText,
            'articles' => $articles,
            'metadata' => $metadata,
            'scraped_at' => now()->toIso8601String(),
            'content_length' => mb_strlen($lawText),
        ];
    }

    /**
     * Scrape content for multiple laws
     *
     * @param array $urls Array of law URLs to scrape
     * @return array
     */
    public function scrapeLawsContent(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            try {
                $results[$url] = $this->scrapeLawContent($url);
                $results[$url]['status'] = 'success';

                // Add a small delay to avoid overwhelming the server
                usleep(500000); // 0.5 second delay
            } catch (\Exception $e) {
                Log::error("Failed to scrape law content", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $results[$url] = [
                    'url' => $url,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
