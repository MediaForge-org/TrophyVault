<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisTest extends TestCase
{
    public function test_redis_is_reachable_through_phpredis(): void
    {
        $this->assertSame('phpredis', config('database.redis.client'));
        $this->assertTrue((bool) Redis::connection()->ping());
    }

    public function test_tests_use_isolated_redis_databases_and_prefix(): void
    {
        $this->assertSame('14', config()->string('database.redis.default.database'));
        $this->assertSame('15', config()->string('database.redis.cache.database'));
        $this->assertSame('trophyvault-test-', config()->string('database.redis.options.prefix'));
    }

    public function test_values_round_trip(): void
    {
        $connection = Redis::connection();
        $connection->set('foundation:probe', 'ok');

        try {
            $this->assertSame('ok', $connection->get('foundation:probe'));
        } finally {
            $connection->del('foundation:probe');
        }
    }
}
