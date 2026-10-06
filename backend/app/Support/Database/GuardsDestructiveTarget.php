<?php

namespace App\Support\Database;

use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

/**
 * Used by the guarded copies of Laravel's destructive commands. Checks the connection the command would really use
 * (`--database` or the default) against {@see DestructiveCommandPolicy} before anything is dropped.
 *
 * Why not an event listener: Laravel does not dispatch CommandStarting when the *label* APP_ENV is "testing"
 * (Kernel skips rerouteSymfonyCommandEvents), which is exactly what `--env=testing` sets. The check therefore lives in
 * the command classes themselves and never looks at the environment label.
 */
trait GuardsDestructiveTarget
{
    protected function assertSafeDatabaseTarget(): void
    {
        $option = $this->option('database');
        $manager = $this->laravel->make('db');
        $connection = is_string($option) && $option !== '' ? $option : $manager->getDefaultConnection();
        // The database the connection will REALLY use: apply the same `url` / DB_URL resolution Laravel applies when it
        // builds the connection (DB_DATABASE=x_test + DB_URL=.../trophyvault must count as "trophyvault"). Done on the
        // current config instead of a cached connection object, so runtime config changes are honoured as well.
        $raw = $this->laravel->make('config')->get("database.connections.{$connection}");
        $config = [];
        if (is_array($raw)) {
            /** @var array<string, mixed> $raw */
            $config = (new ConfigurationUrlParser)->parseConfiguration($raw);
        }
        $database = is_string($config['database'] ?? null) ? $config['database'] : '';
        $allowedFor = $this->laravel->make('config')->get('safety.allow_destructive_for');

        $reason = DestructiveCommandPolicy::denialReason(
            (string) $this->getName(),
            $database,
            $this->laravel->environment(),
            is_string($allowedFor) ? $allowedFor : null,
        );

        if ($reason !== null) {
            throw new RuntimeException("Refusing to run {$this->getName()}: {$reason}.");
        }
    }
}
