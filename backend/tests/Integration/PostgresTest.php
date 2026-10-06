<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_connection_is_postgresql(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_server_is_postgresql_18(): void
    {
        $version = DB::scalar('show server_version_num');

        $this->assertIsNumeric($version);
        $this->assertGreaterThanOrEqual(180000, (int) $version);
    }

    public function test_tests_use_the_dedicated_test_database(): void
    {
        $this->assertSame('trophyvault_test', DB::scalar('select current_database()'));
    }

    public function test_migrations_run_against_postgresql(): void
    {
        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasColumns('failed_jobs', ['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']));
    }

    public function test_database_is_rolled_back_between_tests(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'a', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x',
        ]);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_previous_test_data_does_not_leak(): void
    {
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
