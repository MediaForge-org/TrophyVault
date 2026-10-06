<?php

namespace Tests\Integration\Schema;

use App\Enums\ClassificationOrigin;
use App\Enums\GameRelationKind;
use App\Enums\PairKind;
use App\Enums\ProviderKind;
use App\Enums\PsnAccountStatus;
use App\Enums\TrophyGrade;
use App\Enums\TrophyGroupClassification;
use App\Enums\TrophyRelationKind;
use App\Enums\TrophySetRelationKind;
use BackedEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

/**
 * PHP backed enums and the CHECK constraints they mirror must list exactly the same values (ADR 0008).
 */
class EnumConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, class-string<BackedEnum>}>
     */
    public static function mirroredEnums(): array
    {
        return [
            'trophies.grade' => ['trophies', 'grade', TrophyGrade::class],
            'trophy_groups.classification' => ['trophy_groups', 'classification', TrophyGroupClassification::class],
            'trophy_groups.classification_origin' => ['trophy_groups', 'classification_origin', ClassificationOrigin::class],
            'game_relations.kind' => ['game_relations', 'kind', GameRelationKind::class],
            'trophy_set_relations.kind' => ['trophy_set_relations', 'kind', TrophySetRelationKind::class],
            'trophy_relations.kind' => ['trophy_relations', 'kind', TrophyRelationKind::class],
            'psn_accounts.status' => ['psn_accounts', 'status', PsnAccountStatus::class],
            'providers.kind' => ['providers', 'kind', ProviderKind::class],
        ];
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    #[DataProvider('mirroredEnums')]
    public function test_enum_cases_match_the_check_constraint(string $table, string $column, string $enum): void
    {
        $cases = array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases());
        sort($cases);

        $this->assertSame($cases, SchemaIntrospector::allowedValues($table, $column));
    }

    public function test_every_in_list_check_in_the_schema_has_a_php_enum_mirror(): void
    {
        $mirrored = array_keys(self::mirroredEnums());
        sort($mirrored);

        $this->assertSame($mirrored, SchemaIntrospector::inListColumns(), 'A new "IN (...)" CHECK needs a backed enum and an entry in mirroredEnums()');
    }

    /**
     * @return array<string, array{string, class-string<PairKind&BackedEnum>}>
     */
    public static function relationEnums(): array
    {
        return [
            'game_relations' => ['game_relations', GameRelationKind::class],
            'trophy_set_relations' => ['trophy_set_relations', TrophySetRelationKind::class],
            'trophy_relations' => ['trophy_relations', TrophyRelationKind::class],
        ];
    }

    /**
     * @param  class-string<PairKind&BackedEnum>  $enum
     */
    #[DataProvider('relationEnums')]
    public function test_symmetric_kinds_in_php_equal_the_ordered_pair_check_in_the_database(string $table, string $enum): void
    {
        $symmetric = array_values(array_map(
            fn ($case): string => (string) $case->value,
            array_filter($enum::cases(), fn ($case): bool => $case->isSymmetric()),
        ));
        sort($symmetric);

        $this->assertSame($symmetric, SchemaIntrospector::symmetricKinds($table));
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    #[DataProvider('mirroredEnums')]
    public function test_every_enum_value_is_visible_in_the_generated_data_dictionary(string $table, string $column, string $enum): void
    {
        $path = base_path('../docs/data-model/data-dictionary.md');
        $this->assertFileExists($path);
        $dictionary = (string) file_get_contents($path);

        foreach ($enum::cases() as $case) {
            $this->assertStringContainsString("'{$case->value}'", $dictionary, "{$table}.{$column} value {$case->value} missing in the data dictionary (regenerate it)");
        }
    }
}
