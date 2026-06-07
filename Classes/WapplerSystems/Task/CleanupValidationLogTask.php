<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Task;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Periodically prunes rows from `tx_form_validation_log` that are older
 * than the configured retention window. Companion to the
 * RecordValidationFailures listener — without periodic cleanup the table
 * grows unbounded, which is both a performance and a GDPR concern.
 *
 * Configure the retention window in the Scheduler backend per task
 * instance — sensible values range from 7 (one week) to 365 days.
 * The default is 90 days.
 */
final class CleanupValidationLogTask extends AbstractTask
{
    private const TABLE_NAME = 'tx_form_validation_log';

    protected int $txFormRetentionDays = 90;

    public function execute(): bool
    {
        $retentionDays = max(1, $this->txFormRetentionDays);
        $cutoff = time() - $retentionDays * 86400;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeStatement();

        return true;
    }

    public function setTaskParameters(array $parameters): void
    {
        $this->txFormRetentionDays = (int)($parameters['tx_form_retention_days'] ?? 90);
    }

    public function getAdditionalInformation(): string
    {
        return sprintf('Retention: %d days', $this->txFormRetentionDays);
    }
}
