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

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Form\Domain\DTO\ConsentLogDemand;

/**
 * Raw-SQL access to the consent log and to the wordings it refers to.
 *
 * Not an Extbase repository for the same reason MailLogRepository is not: these
 * tables are an append-only journal, not domain objects, and the module pages
 * through them with a QueryBuilderPaginator.
 *
 * @internal not part of public TYPO3 Core API
 */
class ConsentLogRepository
{
    public const TABLE = 'tx_form_consent_log';
    public const TEXT_TABLE = 'tx_form_consent_text';

    public function __construct(
        protected readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, $row);

        return (int)$connection->lastInsertId();
    }

    /**
     * Stores a wording the first time it is seen and moves `last_seen` forward
     * on every later sighting.
     *
     * The insert races with itself on concurrent submissions, which is what the
     * unique index on `text_hash` is for: the loser's exception is swallowed
     * because the row it wanted is now there anyway.
     */
    public function rememberText(string $hash, string $text, int $languageUid, int $now): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TEXT_TABLE);

        $updated = $connection->update(
            self::TEXT_TABLE,
            ['last_seen' => $now],
            ['text_hash' => $hash],
        );
        if ($updated > 0) {
            return;
        }

        try {
            $connection->insert(self::TEXT_TABLE, [
                'crdate' => $now,
                'last_seen' => $now,
                'text_hash' => $hash,
                'language_uid' => $languageUid,
                'consent_text' => $text,
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // A parallel submission got there first - the wording is recorded,
            // which is all this method promises.
        }
    }

    public function findTextByHash(string $hash): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TEXT_TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TEXT_TABLE)
            ->where($queryBuilder->expr()->eq('text_hash', $queryBuilder->createNamedParameter($hash)))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    public function findByUid(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    /**
     * The other consents recorded for the same submission.
     */
    public function findSiblings(string $submissionId, int $excludeUid): array
    {
        if ($submissionId === '') {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('submission_id', $queryBuilder->createNamedParameter($submissionId)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($excludeUid, ParameterType::INTEGER)),
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function createDemandQueryBuilder(ConsentLogDemand $demand): QueryBuilder
    {
        $queryBuilder = $this->applyDemand($this->connectionPool->getQueryBuilderForTable(self::TABLE), $demand);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC');
    }

    public function countByDemand(ConsentLogDemand $demand): int
    {
        $queryBuilder = $this->applyDemand($this->connectionPool->getQueryBuilderForTable(self::TABLE), $demand);

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return list<string>
     */
    public function findDistinctFormIdentifiers(int $since): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->selectLiteral('DISTINCT ' . $queryBuilder->quoteIdentifier('form_identifier') . ' AS form_identifier')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)))
            ->orderBy('form_identifier', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_filter(array_column($rows, 'form_identifier')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllTexts(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TEXT_TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TEXT_TABLE)
            ->orderBy('last_seen', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function deleteOlderThan(int $cutoff): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $deleted = $connection->delete(self::TABLE, ['crdate' => $cutoff], [Connection::PARAM_INT]);

        return $deleted;
    }

    /**
     * Drops wordings no log row refers to any more.
     *
     * Run after deleteOlderThan(): a wording whose last consent row has just
     * been pruned is evidence for nothing, and keeping it would quietly turn a
     * retention window into "forever" for the one column most likely to contain
     * the visitor-facing text.
     */
    public function deleteOrphanedTexts(): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TEXT_TABLE);

        return (int)$connection->executeStatement(
            'DELETE FROM ' . self::TEXT_TABLE
            . ' WHERE text_hash NOT IN (SELECT DISTINCT text_hash FROM ' . self::TABLE . ')'
        );
    }

    protected function applyDemand(QueryBuilder $queryBuilder, ConsentLogDemand $demand): QueryBuilder
    {
        $expr = $queryBuilder->expr();
        $queryBuilder->where(
            $expr->gte('crdate', $queryBuilder->createNamedParameter($demand->from, ParameterType::INTEGER)),
            $expr->lte('crdate', $queryBuilder->createNamedParameter($demand->to, ParameterType::INTEGER)),
        );

        if ($demand->formIdentifier !== '') {
            $queryBuilder->andWhere(
                $expr->eq('form_identifier', $queryBuilder->createNamedParameter($demand->formIdentifier))
            );
        }

        if ($demand->subject !== '') {
            $queryBuilder->andWhere(
                $expr->like(
                    'subject',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($demand->subject) . '%'
                    )
                )
            );
        }

        if ($demand->givenOnly) {
            $queryBuilder->andWhere($expr->eq('given', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)));
        }
        if ($demand->withheldOnly) {
            $queryBuilder->andWhere($expr->eq('given', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)));
        }

        return $queryBuilder;
    }
}
