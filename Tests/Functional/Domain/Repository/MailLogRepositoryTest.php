<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Pagination\QueryBuilderPaginator;
use TYPO3\CMS\Form\Domain\DTO\MailLogDemand;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Enum\MailLogStatus;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Exercises the queries against a real schema — in particular the derived
 * "stuck" condition, which is the one piece of logic that exists only in SQL.
 */
final class MailLogRepositoryTest extends FunctionalTestCase
{
    private const NOW = 1787000000;

    private MailLogRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(MailLogRepository::class);
    }

    #[Test]
    public function openReturnsTheUidTheUpdateThenTargets(): void
    {
        $uid = $this->subject->open([
            'crdate' => self::NOW,
            'status' => MailLogStatus::PENDING->value,
            'form_identifier' => 'contact',
        ]);
        self::assertGreaterThan(0, $uid);

        $this->subject->update($uid, ['status' => MailLogStatus::SENT->value]);

        $row = $this->subject->findByUid($uid);
        self::assertSame(MailLogStatus::SENT->value, (int)$row['status']);
    }

    #[Test]
    public function twoRowsOpenedInOneRequestGetDistinctUids(): void
    {
        // The recorder relies on this: a form with both an EmailToReceiver and an
        // EmailToSender must not have the second row overwrite the first.
        $first = $this->subject->open(['crdate' => self::NOW, 'form_identifier' => 'contact']);
        $second = $this->subject->open(['crdate' => self::NOW, 'form_identifier' => 'contact']);

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function problemsCoverReportedFailuresAndAbandonedRows(): void
    {
        $sent = $this->row(MailLogStatus::SENT, self::NOW - 3600);
        $failed = $this->row(MailLogStatus::FAILED, self::NOW - 3600);
        $stuck = $this->row(MailLogStatus::PREPARED, self::NOW - MailLogRepository::STUCK_GRACE_SECONDS - 60);
        $inFlight = $this->row(MailLogStatus::PENDING, self::NOW - 5);

        $uids = array_map(
            static fn(array $row): int => (int)$row['uid'],
            $this->subject->findProblems(self::NOW - 86400, null, self::NOW)
        );

        self::assertContains($failed, $uids, 'a reported failure is a problem');
        self::assertContains($stuck, $uids, 'a row abandoned past the grace period is a problem');
        self::assertNotContains($inFlight, $uids, 'a row that just started is not a problem yet');
        self::assertNotContains($sent, $uids);
    }

    #[Test]
    public function theProblemsFilterAndTheCliCheckAgree(): void
    {
        // Both go through problemExpression(); if they ever diverge, an alert and
        // the screen an operator checks it against would disagree.
        $this->row(MailLogStatus::FAILED, self::NOW - 3600);
        $this->row(MailLogStatus::PREPARED, self::NOW - MailLogRepository::STUCK_GRACE_SECONDS - 60);
        $this->row(MailLogStatus::SENT, self::NOW - 3600);

        $demand = MailLogDemand::fromArray(['status' => MailLogDemand::STATUS_PROBLEMS], self::NOW);
        $paginator = new QueryBuilderPaginator(
            $this->subject->createDemandQueryBuilder($demand, self::NOW),
            1,
            20
        );

        self::assertCount(
            count($this->subject->findProblems($demand->from, null, self::NOW)),
            $paginator->getPaginatedItems()
        );
    }

    #[Test]
    public function countByStatusRespectsTheFilter(): void
    {
        $this->row(MailLogStatus::SENT, self::NOW - 3600, 'contact');
        $this->row(MailLogStatus::SENT, self::NOW - 3600, 'newsletter');
        $this->row(MailLogStatus::FAILED, self::NOW - 3600, 'contact');

        $demand = MailLogDemand::fromArray(['formIdentifier' => 'contact'], self::NOW);
        $counts = $this->subject->countByStatus($demand, self::NOW);

        self::assertSame(1, $counts[MailLogStatus::SENT->value] ?? 0);
        self::assertSame(1, $counts[MailLogStatus::FAILED->value] ?? 0);
    }

    #[Test]
    public function rowsOutsideTheWindowAreNotCounted(): void
    {
        $this->row(MailLogStatus::SENT, self::NOW - 86400 * 40);

        $demand = MailLogDemand::fromArray([], self::NOW);
        self::assertSame([], $this->subject->countByStatus($demand, self::NOW));
    }

    #[Test]
    public function paginationSlicesWithoutLoadingEverything(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->row(MailLogStatus::SENT, self::NOW - 3600 - $i);
        }

        $demand = MailLogDemand::fromArray([], self::NOW);
        $paginator = new QueryBuilderPaginator($this->subject->createDemandQueryBuilder($demand, self::NOW), 2, 20);

        // getTotalAmountOfItems() is protected, so assert through the public API:
        // 25 rows at 20 per page is two pages, and the second holds the rest.
        self::assertSame(2, $paginator->getNumberOfPages());
        self::assertCount(5, $paginator->getPaginatedItems());
    }

    #[Test]
    public function countSentSinceSeesAnAbsenceOfRows(): void
    {
        // What --min-sent is for: a form that stopped sending produces no rows,
        // and no list-based check can distinguish that from "all good".
        self::assertSame(0, $this->subject->countSentSince('monitoring', self::NOW - 86400));

        $this->row(MailLogStatus::SENT, self::NOW - 3600, 'monitoring');
        self::assertSame(1, $this->subject->countSentSince('monitoring', self::NOW - 86400));
    }

    #[Test]
    public function siblingsFindTheOtherMailsOfOneSubmission(): void
    {
        $submissionId = 'abcdef0123456789abcdef0123456789';
        $first = $this->row(MailLogStatus::SENT, self::NOW - 10, 'contact', $submissionId);
        $second = $this->row(MailLogStatus::FAILED, self::NOW - 10, 'contact', $submissionId);
        $unrelated = $this->row(MailLogStatus::SENT, self::NOW - 10, 'contact', 'ffffffffffffffffffffffffffffffff');

        $siblings = array_map(static fn(array $r): int => (int)$r['uid'], $this->subject->findSiblings($submissionId, $first));

        self::assertSame([$second], $siblings);
        self::assertNotContains($unrelated, $siblings);
    }

    #[Test]
    public function deleteOlderThanKeepsRecentRows(): void
    {
        $old = $this->row(MailLogStatus::SENT, self::NOW - 86400 * 100);
        $recent = $this->row(MailLogStatus::SENT, self::NOW - 86400);

        $deleted = $this->subject->deleteOlderThan(self::NOW - 86400 * 90);

        self::assertSame(1, $deleted);
        self::assertNull($this->subject->findByUid($old));
        self::assertNotNull($this->subject->findByUid($recent));
    }

    #[Test]
    public function distinctFormIdentifiersSkipsEmptyOnes(): void
    {
        $this->row(MailLogStatus::SENT, self::NOW - 3600, 'contact');
        $this->row(MailLogStatus::SENT, self::NOW - 3600, 'contact');
        $this->row(MailLogStatus::SENT, self::NOW - 3600, '');

        self::assertSame(['contact'], $this->subject->findDistinctFormIdentifiers(self::NOW - 86400));
    }

    private function row(
        MailLogStatus $status,
        int $crdate,
        string $formIdentifier = 'contact',
        string $submissionId = '',
    ): int {
        return $this->subject->open([
            'crdate' => $crdate,
            'status' => $status->value,
            'form_identifier' => $formIdentifier,
            'finisher_identifier' => 'EmailToReceiver',
            'submission_id' => $submissionId,
        ]);
    }
}
