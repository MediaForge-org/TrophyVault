<?php

namespace Tests\Integration\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * Machine-checked project conventions (docs/data-model/conventions.md).
 */
class SchemaConventionsTest extends TestCase
{
    use AssertsDatabaseViolations;
    use RefreshDatabase;

    /** Tables whose primary key is deliberately not a single uuid "id". */
    private const COMPOSITE_OR_NATURAL_KEYS = [
        'providers' => ['code'],
        'platforms' => ['code'],
        'regions' => ['code'],
        'game_release_platforms' => ['game_release_id', 'platform_code'],
        'trophy_set_platforms' => ['trophy_set_id', 'platform_code'],
        'game_release_trophy_sets' => ['game_release_id', 'trophy_set_id'],
        'psn_account_trophy_sets' => ['psn_account_id', 'trophy_set_id'],
        'psn_account_trophies' => ['psn_account_id', 'trophy_id'],
    ];

    /** Laravel infrastructure tables that predate M0.3. */
    private const INFRASTRUCTURE = ['migrations', 'failed_jobs'];

    private const EXPECTED_TABLES = [
        'editions', 'external_identifiers', 'game_relations', 'game_release_platforms', 'game_release_trophy_sets',
        'game_releases', 'games', 'platforms', 'providers', 'psn_account_trophies', 'psn_account_trophy_sets',
        'psn_accounts', 'regions', 'trophies', 'trophy_groups', 'trophy_relations', 'trophy_set_platforms',
        'trophy_set_relations', 'trophy_sets', 'users',
    ];

    /** @return list<string> */
    private function domainTables(): array
    {
        return array_values(array_diff(SchemaIntrospector::tables(), self::INFRASTRUCTURE));
    }

    public function test_the_physical_core_schema_is_exactly_the_documented_one(): void
    {
        $this->assertSame(self::EXPECTED_TABLES, $this->domainTables());
    }

    public function test_every_table_has_a_uuid_primary_key_unless_documented_otherwise(): void
    {
        $primaryKeys = SchemaIntrospector::primaryKeys();
        $types = [];
        foreach (SchemaIntrospector::columns() as $column) {
            $types[$column['table']][$column['column']] = $column['type'];
        }

        foreach ($this->domainTables() as $table) {
            $expected = self::COMPOSITE_OR_NATURAL_KEYS[$table] ?? ['id'];
            $this->assertSame($expected, $primaryKeys[$table] ?? null, "Primary key of {$table}");

            if ($expected === ['id']) {
                $this->assertSame('uuid', $types[$table]['id'], "{$table}.id must be uuid");
            }
        }
    }

    public function test_surrogate_keys_default_to_uuidv7_in_the_database(): void
    {
        foreach (SchemaIntrospector::columns() as $column) {
            if ($column['column'] === 'id' && in_array($column['table'], $this->domainTables(), true)) {
                $this->assertSame('uuidv7()', $column['default'], "{$column['table']}.id default");
            }
        }

        // Inserting without an id works and yields a version-7 UUID.
        DB::table('games')->insert(['slug' => 'raw-insert', 'title' => 'Raw', 'sort_title' => 'raw']);
        $id = DB::table('games')->where('slug', 'raw-insert')->value('id');

        $this->assertIsString($id);
        $this->assertSame('7', $id[14], 'UUID version nibble');
    }

    public function test_all_instants_are_timestamptz_and_there_are_no_soft_delete_columns(): void
    {
        foreach (SchemaIntrospector::columns() as $column) {
            if (! in_array($column['table'], $this->domainTables(), true)) {
                continue;
            }
            $this->assertNotSame('timestamp', $column['type'], "{$column['table']}.{$column['column']} must be timestamptz");
            $this->assertNotSame('deleted_at', $column['column'], "{$column['table']} must not use soft deletes");
        }
    }

    public function test_audit_timestamps_are_not_null_and_default_to_now(): void
    {
        $seen = [];
        foreach (SchemaIntrospector::columns() as $column) {
            if (! in_array($column['table'], $this->domainTables(), true) || ! in_array($column['column'], ['created_at', 'updated_at'], true)) {
                continue;
            }
            $seen[$column['table']][] = $column['column'];
            $this->assertFalse($column['nullable'], "{$column['table']}.{$column['column']} must be NOT NULL");
            $this->assertSame('CURRENT_TIMESTAMP', $column['default'], "{$column['table']}.{$column['column']} default");
        }

        // Every table owns both columns, except pure link tables (no attributes of their own).
        $linkTables = ['game_release_platforms', 'game_release_trophy_sets', 'trophy_set_platforms'];
        $expected = array_values(array_diff($this->domainTables(), $linkTables));
        $this->assertSame($expected, array_keys($seen));
        foreach ($seen as $table => $columns) {
            $this->assertEqualsCanonicalizing(['created_at', 'updated_at'], $columns, $table);
        }
    }

    public function test_the_database_session_runs_in_utc(): void
    {
        $this->assertSame('UTC', DB::scalar('show timezone'));
    }

    public function test_every_jsonb_column_is_not_null_defaults_to_an_object_and_is_object_checked(): void
    {
        $found = 0;
        foreach (SchemaIntrospector::columns() as $column) {
            if ($column['type'] !== 'jsonb' || ! in_array($column['table'], $this->domainTables(), true)) {
                continue;
            }
            $found++;
            $this->assertFalse($column['nullable'], "{$column['table']}.{$column['column']} must be NOT NULL");
            $this->assertStringContainsString("'{}'::jsonb", (string) $column['default']);
            $this->assertTrue(
                (bool) array_filter(
                    SchemaIntrospector::checkDefinitions($column['table']),
                    fn (string $def): bool => str_contains($def, "jsonb_is_safe_object({$column['column']})"),
                ),
                "{$column['table']}.{$column['column']} needs the jsonb_is_safe_object CHECK",
            );
        }
        $this->assertGreaterThan(0, $found);
    }

    /**
     * The referencing side needs an index it can use for cascade/restrict lookups. For composite keys the
     * first column (the entity id) is selective enough; the second column is a same-owner guard.
     */
    public function test_every_foreign_key_is_supported_by_an_index_led_by_its_first_column(): void
    {
        $indexes = SchemaIntrospector::indexes();

        foreach (SchemaIntrospector::foreignKeys() as $fk) {
            if (in_array($fk['table'], self::INFRASTRUCTURE, true)) {
                continue;
            }
            $supported = false;
            foreach ($indexes as $index) {
                if ($index['table'] === $fk['table'] && ($index['columns'][0] ?? null) === $fk['columns'][0]) {
                    $supported = true;
                    break;
                }
            }
            $this->assertTrue($supported, "No index for foreign key {$fk['table']}(".implode(', ', $fk['columns']).')');
        }
    }

    public function test_delete_policy_canonical_parents_are_restricted_and_owned_children_cascade(): void
    {
        $policy = [];
        foreach (SchemaIntrospector::foreignKeys() as $fk) {
            $policy[$fk['table'].'>'.$fk['references'].':'.implode(',', $fk['columns'])] = $fk['on_delete'];
        }

        // Canonical parents cannot be deleted while children exist (retire instead).
        $this->assertSame('restrict', $policy['trophy_sets>games:game_id']);
        $this->assertSame('restrict', $policy['game_releases>games:game_id']);
        $this->assertSame('restrict', $policy['editions>games:game_id']);
        $this->assertSame('restrict', $policy['psn_account_trophy_sets>trophy_sets:trophy_set_id']);
        $this->assertSame('restrict', $policy['psn_account_trophies>trophies:trophy_id,trophy_set_id']);
        // Structural parts and mappings die with their owner.
        $this->assertSame('cascade', $policy['trophies>trophy_sets:trophy_set_id']);
        $this->assertSame('cascade', $policy['trophy_groups>trophy_sets:trophy_set_id']);
        $this->assertSame('cascade', $policy['external_identifiers>games:game_id']);
        // Personal/account data follows its owner.
        $this->assertSame('cascade', $policy['psn_accounts>users:user_id']);
        $this->assertSame('cascade', $policy['psn_account_trophy_sets>psn_accounts:psn_account_id']);
    }
}
