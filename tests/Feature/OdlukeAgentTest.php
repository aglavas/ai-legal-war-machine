<?php

namespace Tests\Feature;

use Tests\TestCase;
use Vizra\VizraADK\Services\AgentRegistry;

class OdlukeAgentTest extends TestCase
{
    public function test_agent_is_registered(): void
    {
        /** @var AgentRegistry $registry */
        $registry = app(AgentRegistry::class);

        // Force discovery
        $agents = $registry->getAllRegisteredAgents();

        $this->assertIsArray($agents);
        $this->assertTrue($registry->hasAgent('odluke_agent'));
    }
}

