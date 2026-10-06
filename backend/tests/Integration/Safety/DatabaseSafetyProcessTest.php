<?php

namespace Tests\Integration\Safety;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RunsProcesses;
use Tests\TestCase;

/**
 * Adversarial database-safety tests (docs/data-model/database-safety.md). Every destructive attempt targets a
 * DECOY database that does not exist, so a broken safeguard fails the test without being able to harm real data.
 * The only code that ever sees the real development database name does read-only work (ConnectionIdentityTest).
 */
class DatabaseSafetyProcessTest extends TestCase
{
    use RunsProcesses;

    private const DECOY = 'decoy_not_a_test_db';

    /**
     * @param  array<string, string>  $env
     * @return array{int, string}
     */
    private function runArtisan(string $command, array $env, string ...$extra): array
    {
        return $this->runProcess(array_values([PHP_BINARY, 'artisan', $command, '--force', ...$extra]), $env);
    }

    private function developmentDatabase(): string
    {
        $test = config()->string('database.connections.pgsql.database');

        return (string) preg_replace('/_test$/', '', $test);
    }

    /** @return array<string, array{string}> */
    public static function destructiveCommands(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh'],
            'migrate:refresh' => ['migrate:refresh'],
            'migrate:reset' => ['migrate:reset'],
            'migrate:rollback' => ['migrate:rollback'],
            'db:wipe' => ['db:wipe'],
        ];
    }

    #[DataProvider('destructiveCommands')]
    public function test_case_b_destructive_commands_are_refused_on_a_non_test_database_even_with_a_testing_label(string $command): void
    {
        foreach (['--env=testing', '--env=local', '--env=staging'] as $flag) {
            [$code, $output] = $this->runArtisan($command, ['DB_DATABASE' => self::DECOY], $flag);

            $this->assertNotSame(0, $code, "{$command} {$flag}");
            $this->assertStringContainsString("Refusing to run {$command}", $output, "{$command} {$flag}");
        }
    }

    #[DataProvider('destructiveCommands')]
    public function test_destructive_commands_are_refused_in_production_even_on_test_named_databases(string $command): void
    {
        [$code, $output] = $this->runArtisan($command, ['DB_DATABASE' => 'decoy_guard_test', 'APP_ENV' => 'production']);

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString("Refusing to run {$command}", $output);
        $this->assertStringContainsString('production', $output);
    }

    public function test_test_named_databases_pass_the_guard(): void
    {
        // The decoy does not exist: the command passes the guard and then fails to connect, which proves the guard let it through.
        [$code, $output] = $this->runArtisan('db:wipe', ['DB_DATABASE' => 'decoy_guard_test']);

        $this->assertNotSame(0, $code);
        $this->assertStringNotContainsString('Refusing to run', $output);
        $this->assertStringContainsString('does not exist', $output);
    }

    public function test_the_explicit_opt_in_must_name_the_exact_target(): void
    {
        $allowed = $this->runArtisan('db:wipe', ['DB_DATABASE' => 'decoy_allowed_db', 'TROPHYVAULT_ALLOW_DESTRUCTIVE_DB' => 'decoy_allowed_db']);
        $this->assertStringNotContainsString('Refusing to run', $allowed[1]);

        foreach (['yes', '1', 'true', '*', 'decoy_other_db'] as $wrong) {
            [$code, $output] = $this->runArtisan('db:wipe', ['DB_DATABASE' => 'decoy_allowed_db', 'TROPHYVAULT_ALLOW_DESTRUCTIVE_DB' => $wrong]);
            $this->assertNotSame(0, $code, $wrong);
            $this->assertStringContainsString('Refusing to run db:wipe', $output, $wrong);
        }
    }

    public function test_the_database_option_cannot_be_used_to_smuggle_a_different_target(): void
    {
        // Default connection is a harmless *_test decoy; --database points at a connection with a non-test name.
        config(['database.connections.hostile' => array_merge(config()->array('database.connections.pgsql'), ['database' => self::DECOY])]);

        foreach (['migrate:fresh', 'db:wipe', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'] as $command) {
            try {
                Artisan::call($command, ['--database' => 'hostile', '--force' => true]);
                $this->fail("{$command} --database=hostile was not refused");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString("Refusing to run {$command}", $e->getMessage());
                $this->assertStringContainsString(self::DECOY, $e->getMessage());
            }
        }
    }

    public function test_an_unknown_connection_name_is_refused_not_waved_through(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run db:wipe');

        Artisan::call('db:wipe', ['--database' => 'no_such_connection', '--force' => true]);
    }

    public function test_the_default_connection_is_checked_too_when_called_in_process(): void
    {
        $original = config()->string('database.connections.pgsql.database');

        try {
            Config::set('database.connections.pgsql.database', self::DECOY);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Refusing to run migrate:fresh');
            Artisan::call('migrate:fresh', ['--force' => true]);
        } finally {
            Config::set('database.connections.pgsql.database', $original);
        }
    }

    public function test_a_database_url_cannot_smuggle_a_non_test_target_past_a_test_looking_database_name(): void
    {
        foreach (['migrate:fresh', 'db:wipe', 'migrate:rollback'] as $command) {
            [$code, $output] = $this->runArtisan($command, [
                'DB_DATABASE' => 'decoy_looks_fine_test',
                'DB_URL' => 'pgsql://decoy:decoy@postgres:5432/'.self::DECOY,
            ]);

            $this->assertNotSame(0, $code, $command);
            $this->assertStringContainsString("Refusing to run {$command}", $output, $command);
            $this->assertStringContainsString(self::DECOY, $output, 'the message names the EFFECTIVE target');
        }
    }

    public function test_a_database_url_to_a_test_database_is_allowed_whatever_the_plain_name_says(): void
    {
        [$code, $output] = $this->runArtisan('db:wipe', [
            'DB_DATABASE' => self::DECOY,
            'DB_URL' => 'pgsql://decoy:decoy@postgres:5432/decoy_url_target_test',
        ]);

        $this->assertNotSame(0, $code);
        $this->assertStringNotContainsString('Refusing to run', $output);
    }

    public function test_case_c_a_wrong_non_test_database_is_rejected_by_the_test_case_itself(): void
    {
        // phpunit.unforced.xml forces nothing and does not switch roles: only Tests\TestCase stands in the way.
        [$code, $output] = $this->runProcess(
            [PHP_BINARY, 'vendor/bin/phpunit', '-c', 'tests/Safety/phpunit.unforced.xml'],
            ['APP_ENV' => 'testing', 'DB_DATABASE' => self::DECOY],
        );

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('Refusing to run tests', $output);
        $this->assertStringContainsString(self::DECOY, $output);
    }

    public function test_case_c_a_non_testing_label_is_rejected_by_the_test_case_itself(): void
    {
        [$code, $output] = $this->runProcess(
            [PHP_BINARY, 'vendor/bin/phpunit', '-c', 'tests/Safety/phpunit.unforced.xml'],
            ['APP_ENV' => 'local', 'DB_DATABASE' => 'decoy_guard_test'],
        );

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('Refusing to run tests', $output);
    }

    public function test_case_b_a_hostile_environment_cannot_redirect_the_suite_to_the_development_database(): void
    {
        $development = $this->developmentDatabase();
        $this->assertStringEndsNotWith('_test', $development);

        // Read-only probe; the forced phpunit.xml must pull the run back to <db>_test whatever the environment says.
        [$code, $output] = $this->runProcess(
            [PHP_BINARY, 'vendor/bin/phpunit', '--filter', 'ConnectionIdentityTest'],
            ['APP_ENV' => 'local', 'DB_DATABASE' => $development, 'DB_URL' => ''],
        );

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('OK (1 test', $output);
    }

    public function test_the_restricted_test_role_cannot_connect_to_the_development_database(): void
    {
        $user = getenv('DB_TEST_USERNAME');
        $password = getenv('DB_TEST_PASSWORD');
        if ($user === false || $user === '' || $password === false) {
            $this->markTestSkipped('No restricted test role configured (CI uses a database that only contains the test data).');
        }

        $this->assertSame($user, DB::scalar('select current_user'));

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', config()->string('database.connections.pgsql.host'), config()->string('database.connections.pgsql.port'), $this->developmentDatabase());
        try {
            new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->fail('The test role connected to the development database.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('permission denied for database', $e->getMessage());
        }
    }
}
