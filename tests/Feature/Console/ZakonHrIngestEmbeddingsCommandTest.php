<?php

namespace Tests\Feature\Console;

use App\Services\ZakonHrIngestService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ZakonHrIngestEmbeddingsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_offline_html_file_not_found_fails(): void
    {
        $this->app->instance(ZakonHrIngestService::class, Mockery::mock(ZakonHrIngestService::class));

        $this->artisan('zakonhr:ingest-embeddings', [
            '--offline-html' => '/tmp/not-existent-'.Str::random(8).'.html',
        ])->expectsOutputToContain('Offline HTML file not found:')
          ->assertExitCode(1);
    }

    public function test_offline_html_success_invokes_service_and_prints_summary(): void
    {
        $html = '<html><head><title>Test law</title></head><body><h1>Law</h1></body></html>';
        $tmp = tempnam(sys_get_temp_dir(), 'html_');
        file_put_contents($tmp, $html);

        $mock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $mock);

        $expectedRes = [
            'urls_processed' => 1,
            'articles_seen' => 3,
            'inserted' => 3,
            'would_chunks' => 3,
            'errors' => 0,
            'model' => 'text-embedding-3-small',
            'dry' => false,
        ];

        $mock->shouldReceive('ingestHtml')->once()->withArgs(function ($argHtml, $opts, $src) use ($html) {
            // Validate the command wires options correctly
            return is_string($argHtml)
                && $argHtml === $html
                && is_array($opts)
                && ($opts['agent'] ?? null) === 'law'
                && ($opts['namespace'] ?? null) === 'zakonhr'
                && ($opts['chunk_chars'] ?? null) === 1200
                && ($opts['overlap'] ?? null) === 150
                && ($opts['dry'] ?? null) === false
                && $src === 'offline://zakonhr-sample';
        })->andReturn($expectedRes);

        $this->artisan('zakonhr:ingest-embeddings', [
            '--offline-html' => $tmp,
        ])->expectsOutputToContain('ZakonHR (offline): Articles=3; Inserted=3; Would-chunks=3; Errors=0; Dry=0')
          ->expectsOutputToContain('Agent=law Namespace=zakonhr Model=text-embedding-3-small')
          ->assertSuccessful();

        @unlink($tmp);
    }

    public function test_urls_success_invokes_service_with_merged_and_deduped_urls(): void
    {
        $mock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $mock);

        // Prepare a list file with duplicates and whitespace
        $listFile = tempnam(sys_get_temp_dir(), 'urls_');
        file_put_contents($listFile, "https://a.example/1\nhttps://b.example/2\nhttps://a.example/1\n\n");

        $expectedUrls = [
            'https://a.example/1',
            'https://x.example/9',
            'https://b.example/2',
        ];

        $mock->shouldReceive('ingestUrls')->once()->withArgs(function ($urls, $opts) use ($expectedUrls) {
            return $urls === $expectedUrls
                && ($opts['agent'] ?? null) === 'custom-agent'
                && ($opts['namespace'] ?? null) === 'zakonhr'
                && ($opts['chunk_chars'] ?? null) === 777
                && ($opts['overlap'] ?? null) === 55
                && ($opts['dry'] ?? null) === true;
        })->andReturn([
            'urls_processed' => 3,
            'articles_seen' => 5,
            'inserted' => 5,
            'would_chunks' => 5,
            'errors' => 0,
            'model' => 'mymodel',
            'dry' => true,
        ]);

        $this->artisan('zakonhr:ingest-embeddings', [
            '--url' => ['https://a.example/1', 'https://x.example/9'],
            '--list' => $listFile,
            '--agent' => 'custom-agent',
            '--chunk' => 777,
            '--overlap' => 55,
            '--dry' => true,
            '--model' => 'mymodel',
        ])->expectsOutputToContain('ZakonHR: URLs processed=3; Articles seen=5; Inserted=5; Would-chunks=5; Errors=0; Dry=1')
          ->expectsOutputToContain('Agent=custom-agent Namespace=zakonhr Model=mymodel')
          ->assertSuccessful();

        @unlink($listFile);
    }

    public function test_missing_urls_and_no_offline_fails_with_message(): void
    {
        $this->app->instance(ZakonHrIngestService::class, Mockery::mock(ZakonHrIngestService::class));

        $this->artisan('zakonhr:ingest-embeddings')
            ->expectsOutputToContain('Provide at least one --url or a --list file, or use --offline-html.')
            ->assertExitCode(1);
    }
}

