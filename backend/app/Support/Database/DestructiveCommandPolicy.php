<?php

namespace App\Support\Database;

/**
 * Decides whether a destructive database command may run against a given database.
 *
 * Rules (first match wins):
 *  1. Never in production (Laravel's confirmation prompt is not a safety boundary).
 *  2. Databases named "*_test" are disposable by convention: allowed.
 *  3. Any other database only with an explicit, *named* opt-in: the allow-list value must equal the
 *     database name (`TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=trophyvault`), so a generic "yes" flag can never
 *     unlock the wrong target.
 * The APP_ENV label is deliberately NOT part of the decision: `--env=testing` changes the label, not the target.
 */
final class DestructiveCommandPolicy
{
    /** Artisan commands that drop or empty data. */
    public const COMMANDS = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe'];

    public const TEST_DATABASE_SUFFIX = '_test';

    public static function isDestructive(string $command): bool
    {
        return in_array($command, self::COMMANDS, true);
    }

    /**
     * @return ?string null when allowed, otherwise the reason for the refusal
     */
    public static function denialReason(string $command, string $database, string $environment, ?string $allowedFor): ?string
    {
        if (! self::isDestructive($command)) {
            return null;
        }

        if ($environment === 'production') {
            return 'destructive database commands are never allowed in production';
        }

        if ($database !== '' && str_ends_with($database, self::TEST_DATABASE_SUFFIX)) {
            return null;
        }

        if ($database !== '' && $allowedFor !== null && $allowedFor !== '' && hash_equals($allowedFor, $database)) {
            return null;
        }

        return sprintf(
            'the target database "%s" is not a test database (name must end in "%s") and was not explicitly allowed '
            .'(set TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=%s, normally via `make db-reset-dev`)',
            $database,
            self::TEST_DATABASE_SUFFIX,
            $database,
        );
    }
}
