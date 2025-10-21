<?php

namespace Tests\Feature\Console;

use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OdlukeIngestEmbeddingsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_offline_text_file_not_found_fails(): void
    {
        $this->app->instance(OdlukeIngestService::class, Mockery::mock(OdlukeIngestService::class));
        $this->app->instance(OdlukeClient::class, Mockery::mock(OdlukeClient::class));

        $this->artisan('odluke:ingest-embeddings', [
            '--offline-text' => '/tmp/not-existent-'.Str::random(8).'.txt',
        ])->expectsOutputToContain('Offline text file not found:')
          ->assertExitCode(1);
    }

    public function test_offline_text_success_invokes_service_and_prints_summary(): void
    {
        $text = 'Odluka suda, primjer teksta.';
        $tmp = tempnam(sys_get_temp_dir(), 'txt_');
        file_put_contents($tmp, $text);

        $svc = Mockery::mock(OdlukeIngestService::class);
        $client = Mockery::mock(OdlukeClient::class);
        $this->app->instance(OdlukeIngestService::class, $svc);
        $this->app->instance(OdlukeClient::class, $client);

        $expectedRes = [
            'ids_processed' => 1,
            'inserted' => 4,
            'would_chunks' => 4,
            'errors' => 0,
            'skipped' => 0,
            'model' => 'odluke-embed',
            'dry' => false,
        ];

        $svc->shouldReceive('ingestText')->once()->withArgs(function ($argText, $meta, $opts) use ($text) {
            return $argText === $text
                && ($meta['id'] ?? null) === 'offline-odluka'
                && ($opts['agent'] ?? null) === 'odluke'
                && ($opts['namespace'] ?? null) === 'odluke'
                && ($opts['chunk_chars'] ?? null) === 1500
                && ($opts['overlap'] ?? null) === 200
                && ($opts['dry'] ?? null) === false;
        })->andReturn($expectedRes);

        $this->artisan('odluke:ingest-embeddings', [
            '--offline-text' => $tmp,
            '--model' => 'odluke-embed',
        ])->expectsOutputToContain('Odluke (offline): Inserted=4; Would-chunks=4; Skipped=0; Errors=0; Dry=0')
          ->expectsOutputToContain('Agent=odluke Namespace=odluke Model=odluke-embed')
          ->assertSuccessful();

        @unlink($tmp);
    }

    public function test_ids_option_parsing_and_service_call(): void
    {
        $svc = Mockery::mock(OdlukeIngestService::class);
        $client = Mockery::mock(OdlukeClient::class);
        $this->app->instance(OdlukeIngestService::class, $svc);
        $this->app->instance(OdlukeClient::class, $client);

        $svc->shouldReceive('ingestByIds')->once()->withArgs(function ($ids, $opts) {
            return $ids === ['abc', 'def', '123']
                && ($opts['agent'] ?? null) === 'agent-x'
                && ($opts['namespace'] ?? null) === 'odluke'
                && ($opts['chunk_chars'] ?? null) === 1600
                && ($opts['overlap'] ?? null) === 180
                && ($opts['prefer'] ?? null) === 'pdf'
                && ($opts['dry'] ?? null) === true
                && ($opts['model'] ?? null) === 'embed-model';
        })->andReturn([
            'ids_processed' => 3,
            'inserted' => 9,
            'would_chunks' => 9,
            'errors' => 0,
            'skipped' => 0,
            'model' => 'embed-model',
            'dry' => true,
        ]);

        $this->artisan('odluke:ingest-embeddings', [
            '--ids' => ' abc , def,123 ',
            '--agent' => 'agent-x',
            '--chunk' => 1600,
            '--overlap' => 180,
            '--prefer' => 'pdf',
            '--dry' => true,
            '--model' => 'embed-model',
        ])->expectsOutputToContain('Odluke: IDs processed=3; Inserted=9; Would-chunks=9; Skipped=0; Errors=0; Dry=1')
          ->expectsOutputToContain('Agent=agent-x Namespace=odluke Model=embed-model')
          ->assertSuccessful();
    }

    public function test_collect_ids_when_ids_missing_and_then_ingest(): void
    {
        $svc = Mockery::mock(OdlukeIngestService::class);
        $client = Mockery::mock(OdlukeClient::class);
        $this->app->instance(OdlukeIngestService::class, $svc);
        $this->app->instance(OdlukeClient::class, $client);

        $client->shouldReceive('collectIdsFromList')->once()->with('pravo', 'type=presuda', 5, 1)
            ->andReturn(['ids' => ['id1', 'id2', 'id3'], 'count' => 3]);

        $svc->shouldReceive('ingestByIds')->once()->with(['id1', 'id2', 'id3'], Mockery::on(function ($opts) {
            return ($opts['prefer'] ?? null) === 'auto' && ($opts['dry'] ?? null) === false;
        }))->andReturn([
            'ids_processed' => 3,
            'inserted' => 6,
            'would_chunks' => 6,
            'errors' => 0,
            'skipped' => 0,
            'model' => 'text-embedding-3-small',
            'dry' => false,
        ]);

        $this->artisan('odluke:ingest-embeddings', [
            '--q' => 'pravo',
            '--params' => 'type=presuda',
            '--limit' => 5,
        ])->expectsOutputToContain('Odluke: IDs processed=3; Inserted=6; Would-chunks=6; Skipped=0; Errors=0; Dry=0')
          ->assertSuccessful();
    }

    public function test_collect_ids_returns_empty_warns_and_exits_failure(): void
    {
        $svc = Mockery::mock(OdlukeIngestService::class);
        $client = Mockery::mock(OdlukeClient::class);
        $this->app->instance(OdlukeIngestService::class, $svc);
        $this->app->instance(OdlukeClient::class, $client);

        $client->shouldReceive('collectIdsFromList')->once()->andReturn(['ids' => []]);

        $this->artisan('odluke:ingest-embeddings', [
            '--q' => 'x',
        ])->expectsOutputToContain('No IDs collected. Provide --ids or use --q/--params or --offline-text.')
          ->assertExitCode(1);
    }
}

