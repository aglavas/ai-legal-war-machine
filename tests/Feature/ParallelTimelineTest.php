<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;
use App\Http\Livewire\ParallelTimeline;

class ParallelTimelineTest extends TestCase
{
    public function test_component_class_can_mount_and_render(): void
    {
        Livewire::test(ParallelTimeline::class)
            ->assertStatus(200);
    }

    public function test_component_can_be_resolved_by_tag_name(): void
    {
        $html = Blade::render('<livewire:parallel-timeline :dataTop="[]" :dataBottom="[]" day="2025-06-09" />');
        $this->assertIsString($html);
        $this->assertStringContainsString('data-pt="parallel"', $html);
    }
}

