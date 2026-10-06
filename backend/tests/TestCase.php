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

        return $app;
    }
}
