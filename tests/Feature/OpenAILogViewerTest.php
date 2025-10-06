<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenAILogViewerTest extends TestCase
{
    public function test_logs_page_renders(): void
    {
        $res = $this->get('/openai/logs');
        $res->assertStatus(200);
        $res->assertSee('OpenAI API Logs');
    }
}

