<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    public static function assertSafe(string $environment, string $database): void
    {
        if ($environment !== 'testing' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests: environment "%s", database "%s". Tests require APP_ENV=testing and a database name ending in "_test".',
                $environment,
                $database,
            ));
        }
    }
}
