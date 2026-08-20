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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Form\Domain\DTO\MailLogDemand;
use TYPO3\CMS\Form\Enum\MailLogStatus;

final class MailLogDemandTest extends TestCase
{
    private const NOW = 1787000000;

    #[Test]
    public function anEmptyFilterDefaultsToTheLastThirtyDays(): void
    {
        // A bounded default matters: the sibling validation-log table already
        // holds five figures, and a first screen that tries to page through
        // everything ever recorded reads as broken.
        $demand = MailLogDemand::fromArray([], self::NOW);

        self::assertSame(self::NOW - 30 * 86400, $demand->from);
        self::assertSame(self::NOW, $demand->to);
        self::assertNull($demand->status);
        self::assertFalse($demand->problemsOnly);
    }

    #[Test]
    public function aDateRangeIsParsedInclusiveOfTheEndDay(): void
    {
        $demand = MailLogDemand::fromArray(['from' => '2026-08-01', 'to' => '2026-08-03'], self::NOW);

        self::assertSame('2026-08-01 00:00:00', date('Y-m-d H:i:s', $demand->from));
        // Without the end-of-day shift, filtering "to 3 August" would silently
        // drop everything that happened on 3 August.
        self::assertSame('2026-08-03 23:59:59', date('Y-m-d H:i:s', $demand->to));
    }

    #[Test]
    public function aReversedRangeIsSwappedRatherThanReturningNothing(): void
    {
        $demand = MailLogDemand::fromArray(['from' => '2026-08-10', 'to' => '2026-08-01'], self::NOW);

        self::assertLessThan($demand->to, $demand->from);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unparsableDateProvider(): array
    {
        return [
            'german format' => ['01.08.2026'],
            'garbage' => ['not-a-date'],
            'empty' => [''],
            'not a string' => [12345],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('unparsableDateProvider')]
    public function anUnparsableDateFallsBackToTheDefaultWindow(mixed $value): void
    {
        $demand = MailLogDemand::fromArray(['from' => $value], self::NOW);

        self::assertSame(self::NOW - 30 * 86400, $demand->from);
    }

    #[Test]
    public function aStatusIsResolvedFromItsNumericValue(): void
    {
        $demand = MailLogDemand::fromArray(['status' => (string)MailLogStatus::FAILED->value], self::NOW);

        self::assertSame(MailLogStatus::FAILED, $demand->status);
        self::assertFalse($demand->problemsOnly);
    }

    #[Test]
    public function anEmptyStatusMeansNoStatusFilter(): void
    {
        // The trap this guards: tryFrom((int)'') resolves to 0, which is a valid
        // case (PENDING) — so an empty filter would silently become "pending only".
        $demand = MailLogDemand::fromArray(['status' => ''], self::NOW);

        self::assertNull($demand->status);
        self::assertFalse($demand->problemsOnly);
    }

    #[Test]
    public function theProblemsPseudoStatusIsItsOwnFlag(): void
    {
        $demand = MailLogDemand::fromArray(['status' => MailLogDemand::STATUS_PROBLEMS], self::NOW);

        self::assertTrue($demand->problemsOnly);
        self::assertNull($demand->status, 'problems is not a single status and must not be mistaken for one');
    }

    #[Test]
    public function anUnknownStatusIsIgnored(): void
    {
        $demand = MailLogDemand::fromArray(['status' => '99'], self::NOW);

        self::assertNull($demand->status);
        self::assertFalse($demand->problemsOnly);
    }

    #[Test]
    public function identifiersAreTrimmed(): void
    {
        $demand = MailLogDemand::fromArray(
            ['formIdentifier' => '  contact  ', 'finisherIdentifier' => " EmailToReceiver\n"],
            self::NOW
        );

        self::assertSame('contact', $demand->formIdentifier);
        self::assertSame('EmailToReceiver', $demand->finisherIdentifier);
    }

    #[Test]
    public function nonStringIdentifiersBecomeEmpty(): void
    {
        $demand = MailLogDemand::fromArray(['formIdentifier' => ['contact']], self::NOW);

        self::assertSame('', $demand->formIdentifier);
    }

    #[Test]
    public function theFilterRoundTripsThroughToArray(): void
    {
        // Pagination and sorting links feed toArray() straight back in, so a
        // round trip has to preserve the selection exactly.
        $input = [
            'from' => '2026-08-01',
            'to' => '2026-08-03',
            'status' => (string)MailLogStatus::SENT->value,
            'formIdentifier' => 'contact',
        ];

        $first = MailLogDemand::fromArray($input, self::NOW);
        $second = MailLogDemand::fromArray($first->toArray(), self::NOW);

        self::assertSame($first->from, $second->from);
        self::assertSame($first->to, $second->to);
        self::assertSame($first->status, $second->status);
        self::assertSame($first->formIdentifier, $second->formIdentifier);
    }

    #[Test]
    public function theProblemsFlagSurvivesTheRoundTrip(): void
    {
        $demand = MailLogDemand::fromArray(['status' => MailLogDemand::STATUS_PROBLEMS], self::NOW);
        $again = MailLogDemand::fromArray($demand->toArray(), self::NOW);

        self::assertTrue($again->problemsOnly);
    }
}
