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
}
