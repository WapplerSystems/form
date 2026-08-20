<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Domain\DTO;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Form\Domain\DTO\ValidationStatsDemand;
use TYPO3\CMS\Form\Enum\ValidationAudience;

final class ValidationStatsDemandTest extends TestCase
{
    private const NOW = 1787000000;

    #[Test]
    public function anEmptyFilterDefaultsToThirtyDaysAndEveryone(): void
    {
        $demand = ValidationStatsDemand::fromArray([], self::NOW);

        self::assertSame(self::NOW - 30 * 86400, $demand->from);
        self::assertSame(self::NOW, $demand->to);
        self::assertSame(ValidationAudience::ALL, $demand->audience);
        self::assertSame('', $demand->formIdentifier);
    }

    #[Test]
    public function anAudienceIsResolvedFromItsName(): void
    {
        $demand = ValidationStatsDemand::fromArray(['audience' => 'humans'], self::NOW);

        self::assertSame(ValidationAudience::HUMANS, $demand->audience);
    }

    #[Test]
    public function anUnknownAudienceFallsBackToEveryone(): void
    {
        // A hand-edited URL must widen the view, never silently narrow it to
        // something the operator did not ask for.
        $demand = ValidationStatsDemand::fromArray(['audience' => 'robots'], self::NOW);

        self::assertSame(ValidationAudience::ALL, $demand->audience);
    }

    #[Test]
    public function theEndOfDayIsIncluded(): void
    {
        $demand = ValidationStatsDemand::fromArray(['from' => '2026-08-01', 'to' => '2026-08-03'], self::NOW);

        self::assertSame('2026-08-01 00:00:00', date('Y-m-d H:i:s', $demand->from));
        self::assertSame('2026-08-03 23:59:59', date('Y-m-d H:i:s', $demand->to));
    }

    #[Test]
    public function aReversedRangeIsSwapped(): void
    {
        $demand = ValidationStatsDemand::fromArray(['from' => '2026-08-10', 'to' => '2026-08-01'], self::NOW);

        self::assertLessThan($demand->to, $demand->from);
    }

    #[Test]
    public function theFilterRoundTrips(): void
    {
        $first = ValidationStatsDemand::fromArray([
            'from' => '2026-08-01',
            'to' => '2026-08-03',
            'audience' => 'bots',
            'formIdentifier' => 'contact',
        ], self::NOW);
        $second = ValidationStatsDemand::fromArray($first->toArray(), self::NOW);

        self::assertSame($first->from, $second->from);
        self::assertSame($first->to, $second->to);
        self::assertSame($first->audience, $second->audience);
        self::assertSame($first->formIdentifier, $second->formIdentifier);
    }

    #[Test]
    public function theBotSignalSetsDoNotOverlap(): void
    {
        // The three audience buckets can only partition the sessions if a code is
        // never both conclusive and merely suspected.
        self::assertSame(
            [],
            array_intersect(ValidationAudience::conclusiveBotCodes(), ValidationAudience::suspectedBotCodes())
        );
    }

    #[Test]
    public function theChallengeFailureIsNotTreatedAsABotSignal(): void
    {
        // 1755648001 fires for every client without a working JavaScript engine,
        // including real visitors who switched it off. Counting it as automation
        // would quietly reclassify people as bots.
        $challengeFailure = 1755648001;

        self::assertNotContains($challengeFailure, ValidationAudience::conclusiveBotCodes());
        self::assertNotContains($challengeFailure, ValidationAudience::suspectedBotCodes());
    }

    #[Test]
    public function anEmptyFieldIsNotTreatedAsABotSignal(): void
    {
        // 1221560718 ("this field is mandatory") is the most human failure there is.
        self::assertNotContains(1221560718, ValidationAudience::conclusiveBotCodes());
        self::assertNotContains(1221560718, ValidationAudience::suspectedBotCodes());
    }
}
