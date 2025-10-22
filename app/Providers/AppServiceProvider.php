<?php

namespace App\Providers;

use App\Http\Livewire\EoglasnaMonitoring;
use App\Http\Livewire\EpredmetWidget;
use App\Http\Livewire\GupTimeline;
use App\Http\Livewire\IngestedLawsManager;
use App\Http\Livewire\TextractManager;
use App\Http\Livewire\TranscriptPreviewer;
use App\Services\OpenAIService;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Http\Livewire\OpenAIVectorManager;
use App\Http\Livewire\OpenAILogViewer;
use App\Services\Odluke\OdlukeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIService::class, function () {
            return new OpenAIService();
        });
        $this->app->singleton(OdlukeClient::class, function () {
            return OdlukeClient::fromConfig();
        });
    }

    public function boot(): void
    {
        Livewire::component('openai-vector-manager', OpenAIVectorManager::class);
        Livewire::component('openai-log-viewer', OpenAILogViewer::class);
        Livewire::component('gup-timeline', GupTimeline::class);
        Livewire::component('ingested-laws-manager', IngestedLawsManager::class);
        Livewire::component('transcript-previewer', TranscriptPreviewer::class);
        Livewire::component('textract-manager', TextractManager::class);
        Livewire::component('epredmet-widget', EpredmetWidget::class);
        Livewire::component('eoglasna-monitoring', EoglasnaMonitoring::class);
    }
}
