<?php

namespace Tests\Integration\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * Index hygiene and index *applicability* for the documented query patterns (docs/data-model/indexes-and-constraints.md).
 * The plans are checked with sequential scans disabled: the question is "can the index serve this query shape?", not
 * what the planner prefers on a small test table. A Sort node in the plan means the index does not deliver the order.
 */
class IndexReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_index_is_a_redundant_prefix_of_another_index(): void
    {
        $indexes = SchemaIntrospector::indexDefinitions();
        $redundant = [];

        foreach ($indexes as $candidate) {
            if ($candidate['partial'] || in_array('(expr)', $candidate['columns'], true)) {
                continue;
            }
            foreach ($indexes as $other) {
                if ($other['name'] === $candidate['name'] || $other['table'] !== $candidate['table'] || $other['partial'] || in_array('(expr)', $other['columns'], true)) {
                    continue;
                }
                $isPrefix = array_slice($other['columns'], 0, count($candidate['columns'])) === $candidate['columns'];
                // A unique/PK index may legitimately be shorter than a wider one (it enforces a rule); a plain index may not.
                if ($isPrefix && ! $candidate['unique'] && count($other['columns']) >= count($candidate['columns'])) {
                    $redundant[] = "{$candidate['name']} is covered by {$other['name']}";
                }
            }
        }

        $this->assertSame([], $redundant);
    }

    public function test_no_two_indexes_have_identical_columns(): void
    {
        $seen = [];
        foreach (SchemaIntrospector::indexDefinitions() as $index) {
            if ($index['partial'] || in_array('(expr)', $index['columns'], true)) {
                continue;
            }
            $key = $index['table'].':'.implode(',', $index['columns']);
            $this->assertFalse(isset($seen[$key]), "{$index['name']} duplicates ".($seen[$key] ?? ''));
            $seen[$key] = $index['name'];
        }
    }

    /** @return string[] */
    private function plan(string $sql): array
    {
        return array_map(fn (object $row): string => (string) (((array) $row)['QUERY PLAN'] ?? ''), DB::select('EXPLAIN '.$sql));
    }

    private function firstId(string $table, int $offset = 0, string $column = 'id', string $order = 'id'): string
    {
        $value = DB::table($table)->orderBy($order)->offset($offset)->limit(1)->value($column);
        $this->assertIsString($value);

        return $value;
    }

    private function seedVolume(): void
    {
        DB::unprepared(<<<'SQL'
            INSERT INTO games (slug, title, sort_title) SELECT 'g-'||i, 'Game '||i, 'game '||lpad(i::text, 5, '0') FROM generate_series(1, 3000) i;
            INSERT INTO trophy_sets (game_id, name) SELECT id, 'Set of '||slug FROM games;
            INSERT INTO trophy_groups (trophy_set_id, set_key, name, ordinal) SELECT id, 'default', 'Base', 0 FROM trophy_sets;
            INSERT INTO trophies (trophy_set_id, trophy_group_id, set_key, grade, name, ordinal)
                SELECT g.trophy_set_id, g.id, n, 'bronze', 'T'||n, n FROM trophy_groups g, generate_series(0, 19) n;
            INSERT INTO external_identifiers (provider_code, namespace, value, trophy_set_id)
                SELECT 'psn', 'np_communication_id', 'NPWR'||lpad(row_number() OVER ()::text, 5, '0')||'_00', id FROM trophy_sets;
            INSERT INTO game_releases (game_id) SELECT id FROM games;
            INSERT INTO game_release_platforms (game_release_id, platform_code) SELECT id, 'ps4' FROM game_releases;
            INSERT INTO game_release_platforms (game_release_id, platform_code) SELECT id, 'ps5' FROM (SELECT id FROM game_releases LIMIT 30) r;
            INSERT INTO game_relations (from_game_id, to_game_id, kind)
                SELECT a.id, b.id, 'sequel_to' FROM (SELECT id, row_number() OVER (ORDER BY id) rn FROM games) a
                JOIN (SELECT id, row_number() OVER (ORDER BY id) rn FROM games) b ON b.rn = a.rn - 1;
            INSERT INTO users (name, email, password) VALUES ('u', 'u@example.test', 'x');
            INSERT INTO psn_accounts (user_id, psn_account_id, online_id) SELECT id, '1', 'player' FROM users;
            INSERT INTO psn_account_trophy_sets (psn_account_id, trophy_set_id, first_seen_at)
                SELECT (SELECT id FROM psn_accounts), id, now() FROM (SELECT id FROM trophy_sets ORDER BY id LIMIT 1500) s;
            INSERT INTO psn_account_trophies (psn_account_id, trophy_id, trophy_set_id, earned_at)
                SELECT (SELECT id FROM psn_accounts), t.id, t.trophy_set_id, now() - (random() * interval '800 days')
                FROM trophies t WHERE t.trophy_set_id IN (SELECT trophy_set_id FROM psn_account_trophy_sets) AND t.set_key < 12;
            ANALYZE;
            SET LOCAL enable_seqscan = off;
            SQL);
    }

    public function test_documented_query_patterns_are_served_by_their_indexes(): void
    {
        $this->seedVolume();
        $set = $this->firstId('trophy_sets');
        $game = $this->firstId('games', 100);
        $account = $this->firstId('psn_accounts');
        $trophy = $this->firstId('psn_account_trophies', 0, 'trophy_id', 'trophy_id');
        $plans = [
            'title substring search' => [$this->plan("SELECT id FROM games WHERE lower(title) LIKE '%game 25%'"), 'games_title_trgm_idx'],
            'library sort' => [$this->plan('SELECT id FROM games ORDER BY sort_title LIMIT 50'), 'games_sort_title_index'],
            'slug lookup' => [$this->plan("SELECT id FROM games WHERE slug = 'g-77'"), 'games_slug_unique'],
            'active external id lookup' => [$this->plan("SELECT trophy_set_id FROM external_identifiers WHERE provider_code = 'psn' AND namespace = 'np_communication_id' AND value = 'NPWR00077_00' AND retired_at IS NULL"), 'external_identifiers_active_unique'],
            'external ids of a set' => [$this->plan("SELECT id FROM external_identifiers WHERE trophy_set_id = '{$set}'"), 'external_identifiers_trophy_set_idx'],
            'sets of a game' => [$this->plan("SELECT id FROM trophy_sets WHERE game_id = '{$game}'"), 'trophy_sets_game_id_index'],
            'groups of a set in order' => [$this->plan("SELECT id FROM trophy_groups WHERE trophy_set_id = '{$set}' ORDER BY ordinal"), 'trophy_groups_trophy_set_id_ordinal_index'],
            'trophies of a set in order' => [$this->plan("SELECT id FROM trophies WHERE trophy_set_id = '{$set}' ORDER BY ordinal"), 'trophies_trophy_set_id_ordinal_index'],
            'earned count of an account in a set' => [$this->plan("SELECT count(*) FROM psn_account_trophies WHERE psn_account_id = '{$account}' AND trophy_set_id = '{$set}'"), 'psn_account_trophies_psn_account_id_trophy_set_id_index'],
            'recently earned' => [$this->plan("SELECT trophy_id FROM psn_account_trophies WHERE psn_account_id = '{$account}' ORDER BY earned_at DESC LIMIT 20"), 'psn_account_trophies_psn_account_id_earned_at_index'],
            'who earned a trophy / delete guard' => [$this->plan("SELECT 1 FROM psn_account_trophies WHERE trophy_id = '{$trophy}'"), 'psn_account_trophies_trophy_id_index'],
            'releases on a platform' => [$this->plan("SELECT game_release_id FROM game_release_platforms WHERE platform_code = 'ps5'"), 'game_release_platforms_platform_code_index'],
            'sequels of a game' => [$this->plan("SELECT from_game_id FROM game_relations WHERE to_game_id = '{$game}' AND kind = 'sequel_to'"), 'game_relations_to_game_id_index'],
            'predecessors of a game' => [$this->plan("SELECT to_game_id FROM game_relations WHERE from_game_id = '{$game}'"), 'game_relations_from_game_id_index'],
            'accounts of a user' => [$this->plan("SELECT id FROM psn_accounts WHERE user_id = '".$this->firstId('users')."'"), 'psn_accounts_user_id_index'],
        ];

        foreach ($plans as $pattern => [$plan, $index]) {
            $text = implode("\n", $plan);
            $this->assertStringContainsString($index, $text, "Pattern '{$pattern}' does not use {$index}:\n{$text}");
        }
    }

    public function test_ordered_patterns_need_no_sort_step(): void
    {
        $this->seedVolume();
        $set = $this->firstId('trophy_sets');
        $account = $this->firstId('psn_accounts');
        // A bitmap scan never delivers index order; forbid it so only an order-preserving index scan can satisfy the query.
        DB::unprepared('SET LOCAL enable_bitmapscan = off');

        foreach ([
            "SELECT id FROM trophies WHERE trophy_set_id = '{$set}' ORDER BY ordinal",
            "SELECT trophy_id FROM psn_account_trophies WHERE psn_account_id = '{$account}' ORDER BY earned_at DESC LIMIT 20",
            'SELECT id FROM games ORDER BY sort_title LIMIT 50',
        ] as $sql) {
            $this->assertStringNotContainsString('Sort', implode("\n", $this->plan($sql)), $sql);
        }
    }

    public function test_an_external_id_lookup_without_the_active_predicate_cannot_use_the_partial_index(): void
    {
        // Documents WHY ExternalIdentifier::active() exists: the unique index is partial (retired_at IS NULL).
        $this->seedVolume();
        $plan = implode("\n", $this->plan("SELECT trophy_set_id FROM external_identifiers WHERE provider_code = 'psn' AND namespace = 'np_communication_id' AND value = 'NPWR00077_00'"));

        $this->assertStringNotContainsString('external_identifiers_active_unique', $plan);
    }
}
