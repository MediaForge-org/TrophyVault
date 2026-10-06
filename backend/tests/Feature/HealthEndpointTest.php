<?php

namespace Tests\Feature;

use App\Health\CheckResult;
use App\Health\HealthChecker;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_application_boots_and_laravel_liveness_route_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_reports_ok_when_all_dependencies_work(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'components' => ['database' => 'ok', 'redis' => 'ok'],
            ]);
    }

    public function test_health_endpoint_returns_503_without_leaking_error_details(): void
    {
        $this->mock(HealthChecker::class)
            ->shouldReceive('check')
            ->once()
            ->andReturn([
                'database' => new CheckResult(false, 'SQLSTATE[08006] password=hunter2 host=db'),
                'redis' => new CheckResult(true),
            ]);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)->assertExactJson([
            'status' => 'degraded',
            'components' => ['database' => 'fail', 'redis' => 'ok'],
        ]);
        $this->assertStringNotContainsString('hunter2', (string) $response->getContent());
    }

    public function test_health_endpoint_returns_json_error_for_unknown_api_routes(): void
    {
        $this->get('/api/does-not-exist')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }
}
