<?php

namespace Tests\Integration\Schema;

use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Identity\PsnAccount;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophySet;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * Every JSONB column is protected by jsonb_is_safe_object(): object only, bounded size, no credential-looking
 * key at any depth, case-insensitive. This is defence in depth against accidents - NOT a secret store, and it
 * cannot see secret *values* (documented in docs/data-model/security-and-performance.md).
 */
class JsonbGuardTest extends TestCase
{
    use AssertsDatabaseViolations;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, Closure(): string}>
     */
    public static function jsonbColumns(): array
    {
        return [
            'games.metadata' => ['games', 'metadata', fn () => (string) Game::factory()->create()->id],
            'game_releases.metadata' => ['game_releases', 'metadata', fn () => (string) GameRelease::factory()->create()->id],
            'trophy_sets.metadata' => ['trophy_sets', 'metadata', fn () => (string) TrophySet::factory()->create()->id],
            'trophies.metadata' => ['trophies', 'metadata', fn () => (string) Trophy::factory()->create()->id],
            'psn_accounts.profile' => ['psn_accounts', 'profile', fn () => (string) PsnAccount::factory()->create()->id],
        ];
    }

    public function test_the_provider_list_covers_every_jsonb_column_in_the_schema(): void
    {
        $actual = [];
        foreach (SchemaIntrospector::columns() as $column) {
            if ($column['type'] === 'jsonb') {
                $actual[] = "{$column['table']}.{$column['column']}";
            }
        }

        $this->assertEqualsCanonicalizing($actual, array_keys(self::jsonbColumns()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenDocuments(): array
    {
        return [
            'top-level key' => ['{"password":"x"}'],
            'upper case' => ['{"ACCESS_TOKEN":"x"}'],
            'mixed case' => ['{"NpSsO":"x"}'],
            'nested object' => ['{"auth":{"refresh_token":"x"}}'],
            'deeply nested' => ['{"a":{"b":{"c":{"d":{"Cookie":"x"}}}}}'],
            'inside array' => ['{"items":[{"ok":1},{"authorization":"Bearer x"}]}'],
            'array in array' => ['{"m":[[{"secret":"x"}]]}'],
            'client secret' => ['{"client_secret":"x"}'],
            'api key' => ['{"api_key":"x"}'],
            'set-cookie' => ['{"set_cookie":"x"}'],
            'array root' => ['[]'],
            'scalar root' => ['"just a string"'],
            'json null' => ['null'],
            'number root' => ['42'],
            'oversized' => ['{"blob":"'.'a'.'"}'],
        ];
    }

    /**
     * @param  Closure(): string  $make
     */
    #[DataProvider('jsonbColumns')]
    public function test_every_jsonb_column_rejects_forbidden_documents(string $table, string $column, Closure $make): void
    {
        $id = $make();

        foreach (self::forbiddenDocuments() as $label => [$json]) {
            if ($label === 'oversized') {
                $json = '{"blob":"'.str_repeat('a', 70000).'"}';
            }
            $this->assertViolation(self::CHECK, fn () => DB::table($table)->where('id', $id)->update([$column => $json]));
        }
    }

    /**
     * @param  Closure(): string  $make
     */
    #[DataProvider('jsonbColumns')]
    public function test_every_jsonb_column_accepts_ordinary_metadata_including_lookalike_keys(string $table, string $column, Closure $make): void
    {
        $id = $make();
        $ok = [
            '{}',
            '{"genre":"action","tokens_total":3,"passwords_policy":"strict","author":"x"}',
            '{"note":"the word token and password in a VALUE is not a key","list":[1,2,{"x":"y"}],"nested":{"deep":{"er":true}}}',
            '{"unicode":"日本語 ✓","empty":[],"nothing":null}',
            '{"blob":"'.str_repeat('a', 60000).'"}',
        ];

        foreach ($ok as $json) {
            DB::table($table)->where('id', $id)->update([$column => $json]);
        }

        $this->assertSame(1, DB::table($table)->where('id', $id)->count());
    }

    public function test_the_guard_function_is_reusable_and_deterministic(): void
    {
        $this->assertTrue((bool) DB::scalar("select jsonb_is_safe_object('{\"a\":1}'::jsonb)"));
        $this->assertFalse((bool) DB::scalar("select jsonb_is_safe_object('{\"a\":{\"TOKEN\":1}}'::jsonb)"));
        $this->assertSame('i', DB::scalar("select provolatile from pg_proc where proname = 'jsonb_is_safe_object'"));
    }
}
