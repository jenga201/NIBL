<?php

namespace Tests\Feature;

use Tests\TestCase;

class UpTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
