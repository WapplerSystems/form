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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Form\Domain\DTO\ValidationStatsDemand;
use TYPO3\CMS\Form\Enum\ValidationAudience;

/**
 * Read-side queries over tx_form_validation_log for the statistics view.
 *
 * The writing side lives in RecordValidationFailures; nothing here writes.
 *
 * Everything is grouped by session rather than by row, because a single
 * automated submission trips several validators at once. Counting rows measures
 * how loud an attack is; counting sessions measures how many actors there are,
 * and only the second number tells you whether you are looking at a traffic
 * problem or at a dozen bots.
 *
 * @internal not part of public TYPO3 Core API
 */
readonly class ValidationLogRepository
{
    public const TABLE_NAME = 'tx_form_validation_log';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Session counts per audience bucket, so the view can show the split — and
     * in particular how many sessions sit in the ambiguous middle instead of
     * pretending the bot/human line is sharp.
     *
     * @return array{bots: int, suspected: int, humans: int, total: int}
     */
    public function countSessionsByAudience(ValidationStatsDemand $demand): array
    {
        $counts = [];
        foreach ([ValidationAudience::BOTS, ValidationAudience::SUSPECTED, ValidationAudience::HUMANS, ValidationAudience::ALL] as $audience) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
            $counts[$audience->value] = (int)$queryBuilder
                ->addSelectLiteral(
                    'COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier('session_hash') . ') AS ' . $queryBuilder->quoteIdentifier('sessions')
                )
                ->from(self::TABLE_NAME)
                ->where(...$this->constraints($queryBuilder, $demand, $audience))
                ->executeQuery()
                ->fetchOne();
        }

        return [
            'bots' => $counts[ValidationAudience::BOTS->value],
            'suspected' => $counts[ValidationAudience::SUSPECTED->value],
            'humans' => $counts[ValidationAudience::HUMANS->value],
            'total' => $counts[ValidationAudience::ALL->value],
        ];
    }

    public function countRows(ValidationStatsDemand $demand): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $demand->audience))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * What went wrong, most frequent first. Grouped by element AND code, because
     * "message is empty" and "message is gibberish" are different problems that
     * happen to share a field.
     *
     * @return list<array{element_identifier: string, error_code: int, hits: int, sessions: int, per_session: float, error_message: string}>
     */
    public function findFailureBreakdown(ValidationStatsDemand $demand, int $limit = 25): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->select('element_identifier', 'error_code')
            ->addSelectLiteral(
                'COUNT(' . $queryBuilder->quoteIdentifier('uid') . ') AS ' . $queryBuilder->quoteIdentifier('hits'),
                'COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier('session_hash') . ') AS ' . $queryBuilder->quoteIdentifier('sessions'),
                'MAX(' . $queryBuilder->quoteIdentifier('error_message') . ') AS ' . $queryBuilder->quoteIdentifier('error_message')
            )
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $demand->audience))
            ->groupBy('element_identifier', 'error_code')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static function (array $row): array {
                $hits = (int)$row['hits'];
                $sessions = (int)$row['sessions'];

                return [
                    'element_identifier' => (string)$row['element_identifier'],
                    'error_code' => (int)$row['error_code'],
                    'hits' => $hits,
                    'sessions' => $sessions,
                    // The tell: a validator a real person trips sits near 1,
                    // while an automated run pushes it into the dozens.
                    'per_session' => $sessions > 0 ? round($hits / $sessions, 1) : 0.0,
                    'error_message' => (string)$row['error_message'],
                ];
            },
            $rows
        );
    }

    /**
     * Rows and sessions per day, for spotting bursts.
     *
     * Grouped with the database's own date formatting rather than in PHP so the
     * whole range does not have to be loaded to draw a few dozen bars.
     *
     * @return list<array{day: string, hits: int, sessions: int}>
     */
    public function findDailyVolume(ValidationStatsDemand $demand): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $dayExpression = $queryBuilder->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform
            ? 'FROM_UNIXTIME(' . $queryBuilder->quoteIdentifier('crdate') . ', \'%Y-%m-%d\')'
            : 'DATE(' . $queryBuilder->quoteIdentifier('crdate') . ', \'unixepoch\')';

        $rows = $queryBuilder
            ->addSelectLiteral(
                $dayExpression . ' AS ' . $queryBuilder->quoteIdentifier('day'),
                'COUNT(' . $queryBuilder->quoteIdentifier('uid') . ') AS ' . $queryBuilder->quoteIdentifier('hits'),
                'COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier('session_hash') . ') AS ' . $queryBuilder->quoteIdentifier('sessions')
            )
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $demand->audience))
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn(array $row): array => [
                'day' => (string)$row['day'],
                'hits' => (int)$row['hits'],
                'sessions' => (int)$row['sessions'],
            ],
            $rows
        );
    }

    /**
     * The busiest sessions, with their span — the view that makes an attack's
     * shape obvious. On the site this was built for it showed ~125 attempts per
     * session over ~15 minutes, i.e. one every 7.0 seconds, identical across
     * sessions. No human pattern looks like that, and it is what justified
     * sizing the fill-time floor above it.
     *
     * @return list<array{session: string, hits: int, span: int, cadence: float, first: int, last: int}>
     */
    public function findBusiestSessions(ValidationStatsDemand $demand, int $limit = 10): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->select('session_hash')
            ->addSelectLiteral(
                'COUNT(' . $queryBuilder->quoteIdentifier('uid') . ') AS ' . $queryBuilder->quoteIdentifier('hits'),
                'MIN(' . $queryBuilder->quoteIdentifier('crdate') . ') AS ' . $queryBuilder->quoteIdentifier('first_seen'),
                'MAX(' . $queryBuilder->quoteIdentifier('crdate') . ') AS ' . $queryBuilder->quoteIdentifier('last_seen')
            )
            ->from(self::TABLE_NAME)
            ->where(...$this->constraints($queryBuilder, $demand, $demand->audience))
            ->groupBy('session_hash')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static function (array $row): array {
                $hits = (int)$row['hits'];
                $span = (int)$row['last_seen'] - (int)$row['first_seen'];

                return [
                    // Truncated: the full value is an HMAC of the session id and
                    // identifies nobody, but a short prefix is all the UI needs to
                    // tell two sessions apart.
                    'session' => substr((string)$row['session_hash'], 0, 10),
                    'hits' => $hits,
                    'span' => $span,
                    'cadence' => $hits > 1 ? round($span / ($hits - 1), 1) : 0.0,
                    'first' => (int)$row['first_seen'],
                    'last' => (int)$row['last_seen'],
                ];
            },
            $rows
        );
    }

    /**
     * @return list<string>
     */
    public function findDistinctFormIdentifiers(int $since): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->selectLiteral('DISTINCT ' . $queryBuilder->quoteIdentifier('form_identifier'))
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)),
            )
            ->orderBy('form_identifier', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_filter(array_map('strval', $rows), static fn(string $v): bool => $v !== ''));
    }

    /**
     * @return list<\TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression|string>
     */
    private function constraints(QueryBuilder $queryBuilder, ValidationStatsDemand $demand, ValidationAudience $audience): array
    {
        $constraints = [
            $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($demand->from, ParameterType::INTEGER)),
            $queryBuilder->expr()->lte('crdate', $queryBuilder->createNamedParameter($demand->to, ParameterType::INTEGER)),
        ];

        if ($demand->formIdentifier !== '') {
            $constraints[] = $queryBuilder->expr()->eq(
                'form_identifier',
                $queryBuilder->createNamedParameter($demand->formIdentifier),
            );
        }

        $audienceConstraint = $this->audienceConstraint($queryBuilder, $audience);
        if ($audienceConstraint !== null) {
            $constraints[] = $audienceConstraint;
        }

        return $constraints;
    }

    /**
     * Turns an audience into a session-membership test.
     *
     * EXISTS/NOT EXISTS correlated on session_hash rather than
     * `session_hash IN (SELECT ...)`: the IN form materialises every matching
     * session before comparing, which on a table that grows by a few hundred rows
     * a day stops being free. Both halves are served by idx_session.
     *
     * The subquery deliberately ignores the outer date range. Session membership
     * is a property of the session, not of the window being looked at — a bot
     * whose honeypot hit falls one day outside the range must not be reclassified
     * as human for the rows inside it.
     */
    private function audienceConstraint(QueryBuilder $queryBuilder, ValidationAudience $audience): ?string
    {
        if ($audience === ValidationAudience::ALL) {
            return null;
        }

        $conclusive = $this->sessionExistsSql($queryBuilder, ValidationAudience::conclusiveBotCodes());

        return match ($audience) {
            ValidationAudience::BOTS => 'EXISTS (' . $conclusive . ')',
            // Only the weak signal, and none of the conclusive ones — otherwise a
            // bot that trips both would be counted twice.
            ValidationAudience::SUSPECTED => 'EXISTS (' . $this->sessionExistsSql($queryBuilder, ValidationAudience::suspectedBotCodes()) . ')'
                . ' AND NOT EXISTS (' . $conclusive . ')',
            ValidationAudience::HUMANS => 'NOT EXISTS (' . $conclusive . ')'
                . ' AND NOT EXISTS (' . $this->sessionExistsSql($queryBuilder, ValidationAudience::suspectedBotCodes()) . ')',
            ValidationAudience::ALL => null,
        };
    }

    /**
     * @param list<int> $errorCodes
     */
    private function sessionExistsSql(QueryBuilder $queryBuilder, array $errorCodes): string
    {
        $alias = 'signal_' . substr(md5(implode(',', $errorCodes)), 0, 6);

        return 'SELECT 1 FROM ' . $queryBuilder->quoteIdentifier(self::TABLE_NAME) . ' ' . $alias
            . ' WHERE ' . $alias . '.' . $queryBuilder->quoteIdentifier('session_hash')
            . ' = ' . $queryBuilder->quoteIdentifier(self::TABLE_NAME) . '.' . $queryBuilder->quoteIdentifier('session_hash')
            . ' AND ' . $alias . '.' . $queryBuilder->quoteIdentifier('error_code')
            . ' IN (' . $queryBuilder->createNamedParameter($errorCodes, ArrayParameterType::INTEGER) . ')';
    }
}
