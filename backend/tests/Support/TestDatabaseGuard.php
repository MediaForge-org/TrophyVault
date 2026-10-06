<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    /**
     * Verifies what the server says about the *live* connection, not what the configuration claims.
     */
    public static function assertConnected(string $currentDatabase, string $currentUser, ?string $expectedUser): void
    {
        if (! str_ends_with($currentDatabase, '_test')) {
            throw new RuntimeException(sprintf('Refusing to run tests: connected to "%s", which is not a *_test database.', $currentDatabase));
        }

        if ($expectedUser !== null && $expectedUser !== '' && $currentUser !== $expectedUser) {
            throw new RuntimeException(sprintf('Refusing to run tests: connected as "%s" instead of the restricted test role "%s".', $currentUser, $expectedUser));
        }
    }

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
