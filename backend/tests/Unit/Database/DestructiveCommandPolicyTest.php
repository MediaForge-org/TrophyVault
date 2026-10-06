<?php

namespace Tests\Unit\Database;

use App\Support\Database\DestructiveCommandPolicy as Policy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DestructiveCommandPolicyTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function destructiveCommands(): array
    {
        return array_combine(Policy::COMMANDS, array_map(fn (string $c): array => [$c], Policy::COMMANDS));
    }

    #[DataProvider('destructiveCommands')]
    public function test_every_destructive_command_is_refused_on_a_non_test_database_whatever_the_environment_label(string $command): void
    {
        foreach (['local', 'testing', 'staging', 'production'] as $environment) {
            $this->assertNotNull(Policy::denialReason($command, 'trophyvault', $environment, null), "{$command} on trophyvault in {$environment}");
        }
    }

    #[DataProvider('destructiveCommands')]
    public function test_test_databases_are_allowed_outside_production(string $command): void
    {
        foreach (['local', 'testing', 'staging'] as $environment) {
            $this->assertNull(Policy::denialReason($command, 'trophyvault_test', $environment, null));
        }
    }

    public function test_production_is_never_allowed_not_even_for_test_named_or_explicitly_allowed_databases(): void
    {
        $this->assertStringContainsString('production', (string) Policy::denialReason('migrate:fresh', 'trophyvault_test', 'production', null));
        $this->assertStringContainsString('production', (string) Policy::denialReason('migrate:fresh', 'trophyvault', 'production', 'trophyvault'));
    }

    public function test_an_explicit_opt_in_must_name_exactly_the_target_database(): void
    {
        $this->assertNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', 'trophyvault'));
        $this->assertNotNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', 'trophyvault_other'));
        $this->assertNotNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', 'true'));
        $this->assertNotNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', '1'));
        $this->assertNotNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', ''));
        $this->assertNotNull(Policy::denialReason('migrate:fresh', 'trophyvault', 'local', '*'));
    }

    public function test_names_that_merely_contain_test_do_not_count(): void
    {
        foreach (['test', 'testdb', 'test_trophyvault', 'trophyvault_testing', 'trophyvault_test_backup', 'trophyvault-test', ''] as $name) {
            $this->assertNotNull(Policy::denialReason('db:wipe', $name, 'local', null), "'{$name}' must not pass");
        }
        $this->assertNull(Policy::denialReason('db:wipe', 'x_test', 'local', null));
    }

    public function test_non_destructive_commands_are_not_restricted(): void
    {
        foreach (['migrate', 'migrate:status', 'db:seed', 'tinker', 'test'] as $command) {
            $this->assertNull(Policy::denialReason($command, 'trophyvault', 'production', null));
            $this->assertFalse(Policy::isDestructive($command));
        }
    }

    public function test_the_denial_message_names_the_target_and_the_way_out(): void
    {
        $reason = (string) Policy::denialReason('migrate:fresh', 'trophyvault', 'local', null);

        $this->assertStringContainsString('"trophyvault"', $reason);
        $this->assertStringContainsString('TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=trophyvault', $reason);
    }
}
