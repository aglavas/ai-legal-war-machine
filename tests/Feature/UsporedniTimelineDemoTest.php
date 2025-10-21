<?php

namespace Tests\Feature;

use Tests\TestCase;

class UsporedniTimelineDemoTest extends TestCase
{
    public function test_demo_route_renders_and_contains_parallel_component(): void
    {
        $resp = $this->get('/usporedni-timeline-demo');
        $resp->assertStatus(200);
        $resp->assertSee('ParallelTimeline', false);
        $resp->assertSee('data-pt="parallel"', false);
    }
}

