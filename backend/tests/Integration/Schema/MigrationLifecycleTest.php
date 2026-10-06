<?php

namespace Tests\Integration\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * Migrations are reversible and re-runnable. PostgreSQL DDL is transactional, so this runs inside the
 * test transaction on the (guarded) test database and leaves nothing behind.
 */
class MigrationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_migrations_roll_back_and_migrate_again(): void
    {
        $before = SchemaIntrospector::tables();
        $this->assertContains('games', $before);

        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertSame(['migrations'], SchemaIntrospector::tables(), 'Rollback must remove every table it created');

        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame($before, SchemaIntrospector::tables());
    }

    public function test_reference_data_is_seeded_by_migrations(): void
    {
        $this->assertSame(
            ['community', 'manual', 'playstationtrophies', 'powerpyx', 'psn', 'psnprofiles'],
            DB::table('providers')->orderBy('code')->pluck('code')->all(),
        );
        $this->assertSame(
            ['pc', 'ps3', 'ps4', 'ps5', 'psp', 'vita'],
            DB::table('platforms')->orderBy('code')->pluck('code')->all(),
        );
        $regions = DB::table('regions')->pluck('code')->all();
        $this->assertContains('global', $regions);
        $this->assertContains('unknown', $regions);
    }

    public function test_every_migration_rolls_back_individually_in_reverse_order_and_leaves_nothing_behind(): void
    {
        $files = glob(database_path('migrations/*.php'));
        $this->assertNotFalse($files);
        $count = count($files);
        $this->assertGreaterThanOrEqual(21, $count);

        $remaining = $count;
        for ($i = 0; $i < $count; $i++) {
            $before = DB::table('migrations')->count();
            Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
            $this->assertSame($before - 1, DB::table('migrations')->count(), 'Each rollback step must undo exactly one migration');
            $remaining--;
        }
        $this->assertSame(0, $remaining);

        // Nothing of ours is left: no tables, no indexes, no application functions.
        $this->assertSame(['migrations'], SchemaIntrospector::tables());
        $this->assertSame(['migrations_pkey'], DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexname')->all());
        $this->assertSame(0, DB::table('pg_proc')->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_proc.pronamespace')->where('nspname', 'public')->where('proname', 'jsonb_is_safe_object')->count());

        // ... and the full chain applies again from nothing.
        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame($count, DB::table('migrations')->count());
        $this->assertSame(1, DB::table('pg_proc')->where('proname', 'jsonb_is_safe_object')->count());
    }

    public function test_migrate_fresh_is_repeatable_because_functions_and_extensions_are_idempotent(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertContains('games', SchemaIntrospector::tables());
        $this->assertSame(1, DB::table('pg_extension')->where('extname', 'pg_trgm')->count());
    }

    public function test_migration_file_names_are_unique_ordered_and_follow_the_naming_rule(): void
    {
        $files = array_map('basename', glob(database_path('migrations/*.php')) ?: []);
        $sorted = $files;
        sort($sorted);

        $this->assertSame($sorted, $files);
        $this->assertSame($files, array_unique($files));
        foreach ($files as $file) {
            $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_(create_[a-z_]+_table|enable_[a-z_]+|create_[a-z_]+_function)\.php$/', $file);
        }
    }
}
