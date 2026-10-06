<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    public function test_accepts_testing_environment_with_test_database(): void
    {
        TestDatabaseGuard::assertSafe('testing', 'trophyvault_test');

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafeCombinations(): array
    {
        return [
            'development database' => ['testing', 'trophyvault'],
            'local environment' => ['local', 'trophyvault_test'],
            'production environment' => ['production', 'trophyvault_test'],
            'empty database name' => ['testing', ''],
            'test only in the middle' => ['testing', 'trophyvault_test_backup'],
        ];
    }

    #[DataProvider('unsafeCombinations')]
    public function test_rejects_unsafe_combinations(string $environment, string $database): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests');

        TestDatabaseGuard::assertSafe($environment, $database);
    }

    public function test_connected_check_accepts_a_test_database_and_the_expected_role(): void
    {
        TestDatabaseGuard::assertConnected('trophyvault_test', 'trophyvault_test', 'trophyvault_test');
        TestDatabaseGuard::assertConnected('trophyvault_test', 'any_role', null);
        TestDatabaseGuard::assertConnected('trophyvault_test', 'any_role', '');

        $this->addToAssertionCount(3);
    }

    public function test_connected_check_rejects_a_live_connection_to_a_non_test_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('connected to "trophyvault"');

        TestDatabaseGuard::assertConnected('trophyvault', 'trophyvault_test', 'trophyvault_test');
    }

    public function test_connected_check_rejects_the_development_role(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted test role');

        TestDatabaseGuard::assertConnected('trophyvault_test', 'trophyvault', 'trophyvault_test');
    }
}
