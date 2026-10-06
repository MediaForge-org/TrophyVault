<?php

namespace Tests\Integration\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * The data-model documentation must describe the schema that is actually migrated
 * (tables, columns, diagrams, roadmap, resolution matrix).
 */
class DocumentationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const INFRASTRUCTURE = ['migrations', 'failed_jobs'];

    private function doc(string $relative): string
    {
        $path = base_path('../docs/'.$relative);
        $content = is_file($path) ? file_get_contents($path) : false;

        if ($content === false) {
            throw new RuntimeException("Cannot read {$path}. The docs folder must be reachable at ../docs (docker compose mounts it read-only).");
        }

        return $content;
    }

    /** @return list<string> */
    private function tables(): array
    {
        return array_values(array_diff(SchemaIntrospector::tables(), self::INFRASTRUCTURE));
    }

    public function test_data_dictionary_documents_every_table_and_every_column(): void
    {
        $dictionary = $this->doc('data-model/data-dictionary.md');
        $sections = preg_split('/^### `/m', $dictionary);
        $this->assertIsArray($sections);
        array_shift($sections);

        $documented = [];
        foreach ($sections as $section) {
            $table = strstr($section, '`', true);
            $this->assertIsString($table);
            $documented[$table] = $section;
        }

        $this->assertEqualsCanonicalizing($this->tables(), array_keys($documented), 'Tables in the data dictionary vs. database');

        $columnsByTable = [];
        foreach (SchemaIntrospector::columns() as $column) {
            $columnsByTable[$column['table']][] = $column['column'];
        }
        foreach ($this->tables() as $table) {
            foreach ($columnsByTable[$table] as $column) {
                $this->assertStringContainsString("| `{$column}` |", $documented[$table], "Column {$table}.{$column} missing in the data dictionary");
            }
            // And no documented column that does not exist.
            preg_match_all('/^\| `([a-z_]+)` \|/m', $documented[$table], $matches);
            $this->assertEqualsCanonicalizing($columnsByTable[$table], $matches[1], "Columns documented for {$table}");
        }
    }

    public function test_er_diagrams_show_every_physical_table(): void
    {
        $diagrams = $this->doc('data-model/er-diagrams.md');
        preg_match_all('/```mermaid\n(.*?)```/s', $diagrams, $blocks);
        $this->assertNotEmpty($blocks[1]);

        foreach ($blocks[1] as $block) {
            $this->assertStringStartsWith('erDiagram', ltrim($block));
            // Entity blocks only (relationship cardinality markers like `o{` are not blocks).
            $this->assertSame(
                preg_match_all('/^\s+\w+ \{$/m', $block),
                preg_match_all('/^\s+\}$/m', $block),
                'Unbalanced entity blocks in a Mermaid diagram',
            );
        }

        $all = implode("\n", $blocks[1]);
        foreach ($this->tables() as $table) {
            $this->assertMatchesRegularExpression('/\b'.$table.'\b/', $all, "Table {$table} is missing in the ER diagrams");
        }
    }

    public function test_logical_model_marks_every_physical_table_as_implemented(): void
    {
        $lines = explode("\n", $this->doc('data-model/logical-model.md'));

        foreach ($this->tables() as $table) {
            $marked = array_filter($lines, fn (string $line): bool => str_contains($line, "`{$table}`") && str_contains($line, '✔'));
            $this->assertNotEmpty($marked, "Table {$table} is not marked ✔ in logical-model.md");
        }
    }

    public function test_migration_roadmap_lists_every_migration_file_and_table(): void
    {
        $roadmap = $this->doc('data-model/migration-roadmap.md');

        foreach ($this->tables() as $table) {
            $this->assertStringContainsString("`{$table}`", $roadmap, "Table {$table} missing in the roadmap");
        }

        $files = glob(database_path('migrations/2026_10_07_*.php'));
        $this->assertNotFalse($files);
        $this->assertCount(20, $files, 'Roadmap documents 20 M0.3 migration files');
        foreach ($files as $file) {
            $name = preg_replace('/^2026_10_07_|\.php$/', '', basename($file));
            $this->assertStringContainsString("`{$name}`", $roadmap, "Migration {$name} missing in the roadmap");
        }
    }

    public function test_index_and_constraint_names_mentioned_in_the_docs_exist(): void
    {
        $names = fn (string $table, string $column): array => DB::table($table)
            ->pluck($column)
            ->map(fn (mixed $name): string => is_string($name) ? $name : '')
            ->all();
        $existing = array_merge($names('pg_indexes', 'indexname'), $names('pg_constraint', 'conname'));

        $checked = 0;
        foreach (['data-model/indexes-and-constraints.md', 'data-model/data-dictionary.md', 'decisions/0011-catalog-and-trophy-structure.md'] as $file) {
            preg_match_all('/`([a-z0-9_]+(?:_index|_unique|_idx|_check|_exclusive|_per_set))`/', $this->doc($file), $matches);
            foreach (array_unique($matches[1]) as $name) {
                $checked++;
                $this->assertContains($name, $existing, "{$file} mentions `{$name}`, which does not exist in the database");
            }
        }
        $this->assertGreaterThan(10, $checked);
    }

    public function test_resolution_matrix_answers_all_27_questions_with_a_valid_status_and_matching_totals(): void
    {
        $matrix = $this->doc('data-model/open-questions-resolution.md');
        preg_match_all('/^\| (\d+) \|.*\| (?:\*\*)?(Resolved|Deferred|Superseded)\b/m', $matrix, $rows, PREG_SET_ORDER);

        $this->assertCount(27, $rows);
        $this->assertSame(range(1, 27), array_map(fn (array $row): int => (int) $row[1], $rows));

        $counts = array_count_values(array_map(fn (array $row): string => $row[2], $rows));
        $this->assertStringContainsString(
            sprintf('%d Resolved · %d Deferred · %d Superseded = 27', $counts['Resolved'] ?? 0, $counts['Deferred'] ?? 0, $counts['Superseded'] ?? 0),
            $matrix,
        );
    }
}
