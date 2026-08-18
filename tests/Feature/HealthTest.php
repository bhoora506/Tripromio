<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * The health endpoint returns HTTP 200.
     */
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }

    /**
     * The health endpoint returns the expected JSON structure.
     */
    public function test_health_endpoint_returns_expected_json_structure(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['status'],
        ]);
    }

    /**
     * The health endpoint data.status is "ok".
     */
    public function test_health_endpoint_data_status_is_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertJson([
            'success' => true,
            'message' => 'Tripromio API is running',
            'data'    => ['status' => 'ok'],
        ]);
    }
}
