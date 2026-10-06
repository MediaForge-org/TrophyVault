<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Guards the configuration assumptions the rest of the platform builds on.
 * Reads the config files with a clean environment so phpunit.xml overrides do not mask defaults.
 */
class ConfigurationTest extends TestCase
{
    public function test_testing_environment_is_active(): void
    {
        $this->assertTrue($this->app->environment('testing'));
    }

    public function test_queue_defaults_to_redis_and_horizon_uses_it(): void
    {
        $queue = $this->freshConfig('queue');

        $this->assertSame('redis', data_get($queue, 'default'));
        $this->assertSame('redis', data_get($queue, 'connections.redis.driver'));
        $this->assertSame('database-uuids', data_get($queue, 'failed.driver'));
        $this->assertSame('default', config()->string('horizon.use'));
        $this->assertIsArray(config('database.redis.default'));
    }

    public function test_cache_and_session_default_to_redis(): void
    {
        $this->assertSame('redis', data_get($this->freshConfig('cache'), 'default'));
        $this->assertSame('redis', data_get($this->freshConfig('session'), 'driver'));
    }

    public function test_database_defaults_to_postgresql(): void
    {
        $database = $this->freshConfig('database');

        $this->assertSame('pgsql', data_get($database, 'default'));
        $this->assertSame('pgsql', data_get($database, 'connections.pgsql.driver'));
    }

    public function test_horizon_has_a_production_supervisor_and_a_dedicated_dashboard_gate(): void
    {
        $this->assertArrayHasKey('production', config()->array('horizon.environments'));
        $this->assertArrayHasKey('local', config()->array('horizon.environments'));
        $this->assertTrue(Gate::has('viewHorizon'));
    }

    public function test_horizon_dashboard_is_denied_to_guests_outside_local(): void
    {
        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    /**
     * Evaluates a config file with the relevant environment variables unset,
     * so phpunit.xml / container overrides do not mask the file's own defaults.
     *
     * @return array<mixed>
     */
    private function freshConfig(string $name): array
    {
        $keys = ['DB_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        try {
            $result = require base_path("config/{$name}.php");
            assert(is_array($result));

            return $result;
        } finally {
            foreach ($saved as $key => [$process, $env, $server]) {
                if ($process !== false) {
                    putenv("{$key}={$process}");
                }
                if ($env !== null) {
                    $_ENV[$key] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}
