<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything that is not clearly a test database.
     *
     * This runs before any test trait (e.g. RefreshDatabase) touches the database,
     * so a misconfigured environment can never wipe the development database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $default = $config->string('database.default');
        $database = $config->string("database.connections.{$default}.database");

        TestDatabaseGuard::assertSafe($app->environment(), $database);

        // Ask the server who we really are (config can lie; the live connection cannot).
        $connection = $app->make('db')->connection();
        $currentDatabase = $connection->scalar('select current_database()');
        $currentUser = $connection->scalar('select current_user');
        $expectedUser = getenv('DB_TEST_USERNAME');
        TestDatabaseGuard::assertConnected(
            is_string($currentDatabase) ? $currentDatabase : '',
            is_string($currentUser) ? $currentUser : '',
            $expectedUser === false ? null : $expectedUser,
        );

        return $app;
    }
}
