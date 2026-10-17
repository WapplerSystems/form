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
 * (`tx_form_submission`). Companion to the SaveSubmission finisher — without
 * periodic cleanup the table grows unbounded, which is both a performance and a
 * GDPR concern for the personal data submissions contain.
 *
 * Unlike the log cleanups this deletes the submitted content itself, so only
 * create a task instance once the retention window is agreed with whoever
 * processes the submissions.
 *
 * Configure the retention window per task instance in the Scheduler backend
 * (field "Retention window (days)"); the default is 90 days.
 */
final class CleanupSubmissionsTask extends AbstractTask
{
    private const TABLE_NAME = 'tx_form_submission';

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

    /**
     * @param array<string, mixed> $parameters
     */
    public function setTaskParameters(array $parameters): void
    {
        $this->txFormRetentionDays = (int)($parameters['tx_form_retention_days'] ?? 90);
    }

    public function getAdditionalInformation(): string
    {
        return sprintf('Retention: %d days', $this->txFormRetentionDays);
    }
}
