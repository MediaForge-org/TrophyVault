<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Typed read access to the PostgreSQL catalog for schema/convention tests.
 */
final class SchemaIntrospector
{
    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(string $sql): array
    {
        $json = DB::scalar("select coalesce(json_agg(t), '[]'::json) from ({$sql}) t");

        if (! is_string($json)) {
            throw new RuntimeException('Unexpected introspection result.');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        return array_map(
            fn (array $row): string => self::str($row['table_name']),
            self::rows("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by 1"),
        );
    }

    /**
     * @return list<array{table: string, column: string, type: string, nullable: bool, default: ?string}>
     */
    public static function columns(): array
    {
        return array_map(fn (array $r): array => [
            'table' => self::str($r['table_name']),
            'column' => self::str($r['column_name']),
            'type' => self::str($r['udt_name']),
            'nullable' => $r['is_nullable'] === 'YES',
            'default' => is_string($r['column_default']) ? $r['column_default'] : null,
        ], self::rows("select table_name, column_name, udt_name, is_nullable, column_default from information_schema.columns where table_schema = 'public' order by 1, ordinal_position"));
    }

    /**
     * @return array<string, list<string>> table => primary key columns
     */
    public static function primaryKeys(): array
    {
        $result = [];
        foreach (self::rows(<<<'SQL'
            select c.relname as table_name, a.attname as column_name
            from pg_index i
            join pg_class c on c.oid = i.indrelid
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = 'public'
            join lateral unnest(i.indkey) with ordinality as k(attnum, ord) on true
            join pg_attribute a on a.attrelid = c.oid and a.attnum = k.attnum
            where i.indisprimary
            order by c.relname, k.ord
            SQL) as $row) {
            $result[self::str($row['table_name'])][] = self::str($row['column_name']);
        }

        return $result;
    }

    /**
     * @return list<array{table: string, columns: list<string>, references: string, on_delete: string}>
     */
    public static function foreignKeys(): array
    {
        return array_map(function (array $r): array {
            $columns = $r['columns'];
            assert(is_array($columns));

            return [
                'table' => self::str($r['table_name']),
                'columns' => array_values(array_map(self::str(...), $columns)),
                'references' => self::str($r['ref_table']),
                'on_delete' => match (self::str($r['confdeltype'])) {
                    'a' => 'no action', 'r' => 'restrict', 'c' => 'cascade', 'n' => 'set null', 'd' => 'set default',
                    default => 'unknown',
                },
            ];
        }, self::rows(<<<'SQL'
            select c.relname as table_name, rc.relname as ref_table, con.confdeltype,
                   (select json_agg(a.attname order by k.ord)
                      from unnest(con.conkey) with ordinality k(attnum, ord)
                      join pg_attribute a on a.attrelid = con.conrelid and a.attnum = k.attnum) as columns
            from pg_constraint con
            join pg_class c on c.oid = con.conrelid
            join pg_class rc on rc.oid = con.confrelid
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = 'public'
            where con.contype = 'f'
            order by 1, 2
            SQL));
    }

    /**
     * Leading plain column of every btree index (expression indexes are skipped), partial or not.
     *
     * @return list<array{table: string, columns: list<string>}>
     */
    public static function indexes(): array
    {
        return array_map(function (array $r): array {
            $columns = $r['columns'];
            assert(is_array($columns));

            return ['table' => self::str($r['table_name']), 'columns' => array_values(array_map(self::str(...), $columns))];
        }, self::rows(<<<'SQL'
            select c.relname as table_name,
                   (select json_agg(a.attname order by k.ord)
                      from unnest(i.indkey) with ordinality k(attnum, ord)
                      join pg_attribute a on a.attrelid = c.oid and a.attnum = k.attnum) as columns
            from pg_index i
            join pg_class c on c.oid = i.indrelid
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = 'public'
            where i.indkey[0] <> 0
            SQL));
    }

    /**
     * @return list<string> definitions of CHECK constraints on a table
     */
    public static function checkDefinitions(string $table): array
    {
        return array_map(
            fn (array $row): string => self::str($row['def']),
            self::rows("select pg_get_constraintdef(con.oid) as def from pg_constraint con join pg_class c on c.oid = con.conrelid where c.relname = '{$table}' and con.contype = 'c'"),
        );
    }

    /**
     * Allowed values of a column's "IN (...)" CHECK constraint, e.g. grade IN ('bronze', ...).
     *
     * @return list<string>
     */
    public static function allowedValues(string $table, string $column): array
    {
        foreach (self::checkDefinitions($table) as $definition) {
            if (! str_contains($definition, "({$column} = ANY")) {
                continue;
            }
            preg_match_all("/'([^']*)'::text/", $definition, $matches);
            $values = $matches[1];
            sort($values);

            return $values;
        }

        throw new RuntimeException("No IN-list CHECK constraint found for {$table}.{$column}");
    }

    private static function str(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new RuntimeException('Expected a scalar value.');
        }

        return (string) $value;
    }

    /**
     * Every "col IN (...)" CHECK of the schema as "table.column" (the PHP enum mirrors must cover all of them).
     *
     * @return list<string>
     */
    public static function inListColumns(): array
    {
        $found = [];
        foreach (self::tables() as $table) {
            foreach (self::checkDefinitions($table) as $definition) {
                if (preg_match('/^CHECK \(\((\w+) = ANY \(ARRAY\[/', $definition, $m) === 1) {
                    $found[] = "{$table}.{$m[1]}";
                }
            }
        }
        sort($found);

        return $found;
    }

    /**
     * Values excluded by "kind <> ALL (ARRAY[...]) OR from < to" style checks, i.e. the symmetric kinds of a relation table.
     *
     * @return list<string>
     */
    public static function symmetricKinds(string $table): array
    {
        foreach (self::checkDefinitions($table) as $definition) {
            // PostgreSQL renders a one-element list as `kind <> 'x'::text` and longer lists as `kind <> ALL (ARRAY[...])`.
            if (preg_match('/kind <> (ALL|\')/', $definition) === 1 && str_contains($definition, ' < ')) {
                preg_match_all("/'([^']*)'::text/", $definition, $matches);
                $values = $matches[1];
                sort($values);

                return $values;
            }
        }

        throw new RuntimeException("No symmetric-order CHECK found for {$table}");
    }

    /**
     * @return list<array{table: string, name: string, unique: bool, partial: bool, columns: list<string>}>
     */
    public static function indexDefinitions(): array
    {
        $rows = self::rows(<<<'SQL'
            select c.relname as table_name, ic.relname as index_name, i.indisunique as is_unique,
                   i.indpred is not null as is_partial, i.indkey::text as keys,
                   (select json_agg(coalesce(a.attname, '(expr)') order by k.ord)
                      from unnest(i.indkey) with ordinality k(attnum, ord)
                      left join pg_attribute a on a.attrelid = c.oid and a.attnum = k.attnum) as columns
            from pg_index i
            join pg_class c on c.oid = i.indrelid
            join pg_class ic on ic.oid = i.indexrelid
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = 'public'
            where c.relname not in ('migrations')
            order by 1, 2
            SQL);

        return array_map(function (array $r): array {
            $columns = $r['columns'];
            assert(is_array($columns));

            return [
                'table' => self::str($r['table_name']),
                'name' => self::str($r['index_name']),
                'unique' => $r['is_unique'] === true,
                'partial' => $r['is_partial'] === true,
                'columns' => array_values(array_map(self::str(...), $columns)),
            ];
        }, $rows);
    }
}
