<?php

namespace Tests\Integration\Catalog;

use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Trophies\TrophySet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\TestCase;

class ExternalIdentifierTest extends TestCase
{
    use AssertsDatabaseViolations;
    use RefreshDatabase;

    public function test_an_identifier_belongs_to_exactly_one_entity(): void
    {
        $game = Game::factory()->create();
        $set = TrophySet::factory()->for($game)->create();
        $base = ['provider_code' => 'psn', 'namespace' => 'np_communication_id', 'value' => 'NPWR00001_00'];

        $this->assertViolation(self::CHECK, fn () => ExternalIdentifier::query()->create($base));
        $this->assertViolation(self::CHECK, fn () => ExternalIdentifier::query()->create($base + ['game_id' => $game->id, 'trophy_set_id' => $set->id]));

        $ok = ExternalIdentifier::query()->create($base + ['trophy_set_id' => $set->id]);
        $this->assertSame($set->id, $ok->trophySet?->id);
    }

    public function test_identifiers_exist_for_games_releases_and_trophy_sets(): void
    {
        $game = Game::factory()->create();
        $release = GameRelease::factory()->for($game)->create();
        $set = TrophySet::factory()->for($game)->create();

        ExternalIdentifier::factory()->create(['namespace' => 'np_communication_id', 'value' => 'NPWR1', 'game_id' => null, 'trophy_set_id' => $set->id]);
        ExternalIdentifier::factory()->create(['namespace' => 'title_id', 'value' => 'CUSA00419', 'game_id' => null, 'game_release_id' => $release->id]);
        ExternalIdentifier::factory()->create(['namespace' => 'title_id', 'value' => 'PPSA03420', 'game_id' => null, 'game_release_id' => $release->id]);
        ExternalIdentifier::factory()->create(['provider_code' => 'psnprofiles', 'namespace' => 'slug', 'value' => '1234-gta-v', 'game_id' => $game->id]);

        $this->assertCount(2, $release->externalIdentifiers);
        $this->assertCount(1, $set->externalIdentifiers);
        $this->assertCount(1, $game->externalIdentifiers);
    }

    public function test_an_active_identifier_maps_to_one_entity_only(): void
    {
        $set = TrophySet::factory()->create();
        $other = TrophySet::factory()->create();
        $attrs = ['provider_code' => 'psn', 'namespace' => 'np_communication_id', 'value' => 'NPWR12345_00', 'game_id' => null];
        ExternalIdentifier::factory()->create($attrs + ['trophy_set_id' => $set->id]);

        $this->assertViolation(self::UNIQUE, fn () => ExternalIdentifier::factory()->create($attrs + ['trophy_set_id' => $other->id]));
    }

    public function test_the_same_value_may_exist_in_another_namespace_or_provider(): void
    {
        $game = Game::factory()->create();
        ExternalIdentifier::factory()->create(['namespace' => 'a', 'value' => '42', 'game_id' => $game->id]);
        ExternalIdentifier::factory()->create(['namespace' => 'b', 'value' => '42', 'game_id' => $game->id]);
        ExternalIdentifier::factory()->create(['provider_code' => 'manual', 'namespace' => 'a', 'value' => '42', 'game_id' => $game->id]);

        $this->assertSame(3, ExternalIdentifier::query()->count());
    }

    public function test_a_retired_identifier_can_be_reassigned(): void
    {
        $old = Game::factory()->create();
        $new = Game::factory()->create();
        $attrs = ['namespace' => 'title_id', 'value' => 'CUSA99999'];
        $first = ExternalIdentifier::factory()->create($attrs + ['game_id' => $old->id]);

        $first->update(['retired_at' => now()]);
        ExternalIdentifier::factory()->create($attrs + ['game_id' => $new->id]);

        $this->assertSame([$new->id], ExternalIdentifier::query()->active()->where($attrs)->pluck('game_id')->all());
        $this->assertSame(2, ExternalIdentifier::query()->where($attrs)->count());
    }

    public function test_provider_must_exist_and_namespace_and_value_are_validated(): void
    {
        $game = Game::factory()->create();
        $make = fn (array $over) => ExternalIdentifier::query()->create($over + [
            'provider_code' => 'psn', 'namespace' => 'ns', 'value' => 'v', 'game_id' => $game->id,
        ]);

        $this->assertViolation(self::FOREIGN_KEY, fn () => $make(['provider_code' => 'nope']));
        $this->assertViolation(self::CHECK, fn () => $make(['namespace' => 'Bad Namespace']));
        $this->assertViolation(self::CHECK, fn () => $make(['value' => '']));
    }

    public function test_identifiers_are_removed_with_their_entity(): void
    {
        $game = Game::factory()->create();
        ExternalIdentifier::factory()->create(['game_id' => $game->id]);

        $game->delete();

        $this->assertSame(0, DB::table('external_identifiers')->count());
    }

    public function test_domain_ids_are_not_provider_ids(): void
    {
        $set = TrophySet::factory()->create();
        ExternalIdentifier::factory()->create(['namespace' => 'np_communication_id', 'value' => 'NPWR00001_00', 'game_id' => null, 'trophy_set_id' => $set->id]);

        $this->assertNotSame('NPWR00001_00', $set->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $set->id);
    }

    public function test_the_exclusive_arc_rejects_zero_two_and_three_targets_with_real_foreign_keys(): void
    {
        $game = Game::factory()->create();
        $release = GameRelease::factory()->for($game)->create();
        $set = TrophySet::factory()->for($game)->create();
        $base = ['provider_code' => 'psn', 'namespace' => 'n', 'value' => 'v'];
        $insert = fn (array $targets) => DB::table('external_identifiers')->insert($base + $targets);

        $this->assertViolation(self::CHECK, fn () => $insert([]));
        $this->assertViolation(self::CHECK, fn () => $insert(['game_id' => $game->id, 'game_release_id' => $release->id]));
        $this->assertViolation(self::CHECK, fn () => $insert(['game_release_id' => $release->id, 'trophy_set_id' => $set->id]));
        $this->assertViolation(self::CHECK, fn () => $insert(['game_id' => $game->id, 'game_release_id' => $release->id, 'trophy_set_id' => $set->id]));
        // and the single target must really exist
        $this->assertViolation(self::FOREIGN_KEY, fn () => $insert(['game_id' => '01a11229-f3ea-73c7-9d79-37f2952bcc25']));
        $this->assertViolation(self::FOREIGN_KEY, fn () => $insert(['trophy_set_id' => $game->id])); // a game id is not a trophy set id
    }

    public function test_deleting_a_provider_that_issued_identifiers_is_restricted(): void
    {
        ExternalIdentifier::factory()->create(['provider_code' => 'psnprofiles']);

        $this->assertViolation(self::RESTRICT, fn () => DB::table('providers')->where('code', 'psnprofiles')->delete());
        $this->assertSame(1, DB::table('providers')->where('code', 'psnprofiles')->count());
    }

    public function test_an_unused_provider_row_can_be_deleted_but_reference_codes_cannot_be_changed_under_identifiers(): void
    {
        ExternalIdentifier::factory()->create(['provider_code' => 'psn']);

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('providers')->where('code', 'psn')->update(['code' => 'psn2']));
    }

    public function test_unretiring_an_old_mapping_cannot_create_two_active_mappings(): void
    {
        $attrs = ['provider_code' => 'psn', 'namespace' => 'title_id', 'value' => 'CUSA00001'];
        $old = ExternalIdentifier::factory()->retired()->create($attrs);
        ExternalIdentifier::factory()->create($attrs);

        $this->assertViolation(self::UNIQUE, fn () => $old->update(['retired_at' => null]));
    }

    public function test_surrounding_whitespace_cannot_create_a_second_spelling_of_the_same_id(): void
    {
        $game = Game::factory()->create();
        $make = fn (string $value) => ExternalIdentifier::query()->create(['provider_code' => 'psn', 'namespace' => 'n', 'value' => $value, 'game_id' => $game->id]);

        $this->assertViolation(self::CHECK, fn () => $make(' NPWR1_00'));
        $this->assertViolation(self::CHECK, fn () => $make('NPWR1_00
'));
        $this->assertViolation(self::CHECK, fn () => $make(' '));
    }

    public function test_namespaces_are_case_and_format_restricted_so_they_cannot_fork_by_spelling(): void
    {
        $game = Game::factory()->create();

        foreach (['NpCommunicationId', 'np-communication-id', 'np communication id', '1abc', ''] as $namespace) {
            $this->assertViolation(self::CHECK, fn () => ExternalIdentifier::query()->create([
                'provider_code' => 'psn', 'namespace' => $namespace, 'value' => 'v', 'game_id' => $game->id,
            ]));
        }
    }

    public function test_retired_identifiers_still_resolve_for_history_but_not_for_active_lookups(): void
    {
        $set = TrophySet::factory()->create();
        ExternalIdentifier::factory()->retired()->create(['provider_code' => 'psn', 'namespace' => 'np_communication_id', 'value' => 'NPWR9', 'game_id' => null, 'trophy_set_id' => $set->id]);

        $this->assertSame(0, ExternalIdentifier::query()->active()->where('value', 'NPWR9')->count());
        $this->assertSame(1, ExternalIdentifier::query()->where('value', 'NPWR9')->count());
    }
}
