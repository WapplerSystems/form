<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Task;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Enforces a retention window on stored form submissions
 * (`tx_form_submission`) and webhook delivery logs (`tx_form_webhook_log`).
 * Companion to the SaveSubmission and Webhook finishers — without periodic
 * cleanup these tables grow unbounded, which is both a performance and a GDPR
 * concern for the personal data submissions may contain.
 *
 * Configure the retention window per task instance in the Scheduler backend
 * (field "Retention window (days)"); the default is 90 days.
 */
final class CleanupSubmissionsTask extends AbstractTask
{
    private const TABLES = [
        'tx_form_submission',
        'tx_form_webhook_log',
    ];

    protected int $txFormRetentionDays = 90;

    public function execute(): bool
    {
        $retentionDays = max(1, $this->txFormRetentionDays);
        $cutoff = time() - $retentionDays * 86400;
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        foreach (self::TABLES as $table) {
            $queryBuilder = $connectionPool->getQueryBuilderForTable($table);
            $queryBuilder
                ->delete($table)
                ->where(
                    $queryBuilder->expr()->lt(
                        'crdate',
                        $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER),
                    ),
                )
                ->executeStatement();
        }

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
