<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Form\Domain\DTO\MailLogDemand;
use TYPO3\CMS\Form\Enum\MailLogStatus;

/**
 * All SQL for tx_form_mail_log lives here — shared by the recorder that writes
 * rows, the backend module that lists them, the CLI check and the cleanup task.
 *
 * The table has no TCA on purpose (like tx_form_validation_log), so there is no
 * DataHandler involvement, no reference index and no workspace overlay to worry
 * about. The flip side is that everything goes through raw queries, which is
 * exactly why it is confined to this class.
 *
 * @internal not part of public TYPO3 Core API
 */
readonly class MailLogRepository
{
    public const TABLE_NAME = 'tx_form_mail_log';

    /**
     * How long a row may sit in a non-terminal status before it counts as
     * abandoned. Generous on purpose: a slow SMTP handshake or a large
     * attachment upload can legitimately keep a row PREPARED for a while, and a
     * false "aborted" is worse than a late one.
     */
    public const STUCK_GRACE_SECONDS = 900;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Opens a row and returns its uid.
     *
     * insert() and lastInsertId() are deliberately adjacent: lastInsertId is
     * per-connection, and nothing else can run on this connection in between, so
     * no correlation column is needed to find the row again.
     *
     * @param array<string, mixed> $row
     */
    public function open(array $row): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $connection->insert(self::TABLE_NAME, $row);

        return (int)$connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $uid, array $fields): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(self::TABLE_NAME, $fields, ['uid' => $uid]);
    }

    /**
     * Deletes rows older than the cutoff and returns how many went.
     */
    /**
     * Number of rows a deleteOlderThan() with the same cutoff would remove.
     */
    public function countOlderThan(int $cutoff): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }

    public function deleteOlderThan(int $cutoff): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int)$queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeStatement();
    }

    /**
     * The listing query for the backend module.
     *
     * Returns the QueryBuilder rather than rows, because QueryBuilderPaginator
     * needs to own limit/offset — it applies setMaxResults/setFirstResult itself
     * and wraps a COUNT around the rest. That is the whole reason this module
     * does not copy FormManagerController's ArrayPaginator: loading five figures
     * of rows to display twenty is not a thing to imitate.
     */
    public function createDemandQueryBuilder(MailLogDemand $demand, ?int $now = null): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $now))
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC');

        return $queryBuilder;
    }

    /**
     * Row counts per status for the current filter, for the summary badges.
     * Served by idx_status, so it stays cheap as the table grows.
     *
     * @return array<int, int> status value => count
     */
    public function countByStatus(MailLogDemand $demand, ?int $now = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->select('status')
            ->addSelectLiteral($queryBuilder->expr()->count('uid', 'total'))
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $now))
            ->groupBy('status')
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['status']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * Form identifiers that actually appear in the log, for the filter dropdown.
     * Deliberately scoped to a window rather than the whole table so a form that
     * was removed a year ago stops cluttering the list.
     *
     * @return list<string>
     */
    public function findDistinctFormIdentifiers(int $since): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->selectLiteral('DISTINCT ' . $queryBuilder->quoteIdentifier('form_identifier'))
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->gte(
                    'crdate',
                    $queryBuilder->createNamedParameter($since, ParameterType::INTEGER),
                ),
            )
            ->orderBy('form_identifier', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_filter(array_map('strval', $rows), static fn(string $v): bool => $v !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUid(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    /**
     * The other mails of the same submission — a form can carry both an
     * EmailToReceiver and an EmailToSender, and "the notification went out but
     * the confirmation did not" is a distinction worth seeing.
     *
     * @return list<array<string, mixed>>
     */
    public function findSiblings(string $submissionId, int $excludeUid): array
    {
        if ($submissionId === '') {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('submission_id', $queryBuilder->createNamedParameter($submissionId)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($excludeUid, ParameterType::INTEGER)),
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<\TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression|string>
     */
    private function constraints(QueryBuilder $queryBuilder, MailLogDemand $demand, ?int $now): array
    {
        $now ??= time();
        $constraints = [
            $queryBuilder->expr()->gte(
                'crdate',
                $queryBuilder->createNamedParameter($demand->from, ParameterType::INTEGER),
            ),
            $queryBuilder->expr()->lte(
                'crdate',
                $queryBuilder->createNamedParameter($demand->to, ParameterType::INTEGER),
            ),
        ];

        if ($demand->problemsOnly) {
            $constraints[] = $this->problemExpression($queryBuilder, $now);
        } elseif ($demand->status !== null) {
            $constraints[] = $queryBuilder->expr()->eq(
                'status',
                $queryBuilder->createNamedParameter($demand->status->value, ParameterType::INTEGER),
            );
        }

        if ($demand->formIdentifier !== '') {
            $constraints[] = $queryBuilder->expr()->eq(
                'form_identifier',
                $queryBuilder->createNamedParameter($demand->formIdentifier),
            );
        }
        if ($demand->finisherIdentifier !== '') {
            $constraints[] = $queryBuilder->expr()->eq(
                'finisher_identifier',
                $queryBuilder->createNamedParameter($demand->finisherIdentifier),
            );
        }

        return $constraints;
    }

    /**
     * "Needs attention" = reported failure, or abandoned in a non-terminal
     * status for longer than the grace period.
     *
     * The stuck half is derived here rather than written by a sweep task on
     * purpose: a monitoring feature that only tells the truth once someone
     * remembers to schedule a second task is a monitoring feature that lies.
     */
    private function problemExpression(QueryBuilder $queryBuilder, int $now): CompositeExpression
    {
        return $queryBuilder->expr()->or(
            $queryBuilder->expr()->eq(
                'status',
                $queryBuilder->createNamedParameter(MailLogStatus::FAILED->value, ParameterType::INTEGER),
            ),
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->in(
                    'status',
                    $queryBuilder->createNamedParameter(
                        [MailLogStatus::PENDING->value, MailLogStatus::PREPARED->value],
                        ArrayParameterType::INTEGER,
                    ),
                ),
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter($now - self::STUCK_GRACE_SECONDS, ParameterType::INTEGER),
                ),
            ),
        );
    }

    /**
     * Rows that need someone's attention, for the CLI check.
     *
     * Shares problemExpression() with the module's "problems" filter on purpose:
     * if the definition of "problem" lived twice, the alert and the screen an
     * operator checks it against would eventually disagree.
     *
     * @return list<array<string, mixed>>
     */
    public function findProblems(int $since, ?string $formIdentifier = null, ?int $now = null): array
    {
        $now ??= time();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $constraints = [
            $queryBuilder->expr()->gte(
                'crdate',
                $queryBuilder->createNamedParameter($since, ParameterType::INTEGER),
            ),
            $this->problemExpression($queryBuilder, $now),
        ];

        if ($formIdentifier !== null && $formIdentifier !== '') {
            $constraints[] = $queryBuilder->expr()->eq(
                'form_identifier',
                $queryBuilder->createNamedParameter($formIdentifier),
            );
        }

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(...$constraints)
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * How many mails a form successfully handed to the transport since a point
     * in time. Used by the CLI check's --min-sent: for a form that is supposed
     * to send on a schedule, the *absence* of rows is the alert, and no
     * list-based check can see an absence.
     */
    public function countSentSince(string $formIdentifier, int $since): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'form_identifier',
                    $queryBuilder->createNamedParameter($formIdentifier),
                ),
                $queryBuilder->expr()->eq(
                    'status',
                    $queryBuilder->createNamedParameter(MailLogStatus::SENT->value, ParameterType::INTEGER),
                ),
                $queryBuilder->expr()->gte(
                    'crdate',
                    $queryBuilder->createNamedParameter($since, ParameterType::INTEGER),
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }
}
