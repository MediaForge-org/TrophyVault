<?php

namespace Tests\Integration\Safety;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Read-only identity probe. Run in a subprocess by DatabaseSafetyProcessTest under hostile environments; it never
 * writes, so it is safe even if a safeguard were broken. Also a plain regression test in the normal suite.
 */
class ConnectionIdentityTest extends TestCase
{
    public function test_the_suite_is_connected_to_the_test_database_in_testing_mode(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $database = DB::scalar('select current_database()');
        $this->assertIsString($database);
        $this->assertStringEndsWith('_test', $database);
        $this->assertSame(config('database.connections.pgsql.database'), $database);
    }
}
