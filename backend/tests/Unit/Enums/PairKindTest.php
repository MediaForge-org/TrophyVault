<?php

namespace Tests\Unit\Enums;

use App\Enums\GameRelationKind;
use App\Enums\TrophyRelationKind;
use App\Enums\TrophySetRelationKind;
use PHPUnit\Framework\TestCase;

class PairKindTest extends TestCase
{
    public function test_game_relation_symmetry(): void
    {
        $this->assertSame(['related'], $this->symmetric(GameRelationKind::cases()));
    }

    public function test_trophy_set_relation_symmetry(): void
    {
        $this->assertEqualsCanonicalizing(
            ['platform_stack', 'regional_stack', 'remaster_stack', 'equivalent', 'related'],
            $this->symmetric(TrophySetRelationKind::cases()),
        );
        $this->assertFalse(TrophySetRelationKind::Autopop->isSymmetric());
        $this->assertFalse(TrophySetRelationKind::PartialAutopop->isSymmetric());
    }

    public function test_trophy_relation_symmetry(): void
    {
        $this->assertEqualsCanonicalizing(['equivalent', 'similar', 'related'], $this->symmetric(TrophyRelationKind::cases()));
        $this->assertFalse(TrophyRelationKind::Replaces->isSymmetric());
    }

    /**
     * @param  list<GameRelationKind|TrophySetRelationKind|TrophyRelationKind>  $cases
     * @return list<string>
     */
    private function symmetric(array $cases): array
    {
        return array_values(array_map(
            fn ($case): string => $case->value,
            array_filter($cases, fn ($case): bool => $case->isSymmetric()),
        ));
    }
}
