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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Repository\ConsentLogRepository;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Prunes rows from `tx_form_consent_log`, and the wordings nothing refers to
 * any more.
 *
 * The default retention is three years, not the 90 days its two sibling tasks
 * use, and the difference is the whole point: this table is evidence. A consent
 * is challenged long after it was given, so pruning it on a monitoring-log
 * schedule would destroy exactly the record the log was built to keep — a
 * self-inflicted failure of Art. 7(1) GDPR dressed up as data hygiene.
 *
 * Three years is a starting point, not advice: it is the German regelmäßige
 * Verjährungsfrist (§ 195 BGB), which is a common yardstick for how long a
 * claim can still surface. The right window follows from the purpose the
 * consent was given for and belongs to whoever runs the site — which is also
 * why this task ships unconfigured rather than being scheduled automatically.
 *
 * Storage limitation (Art. 5(1)(e)) still applies, so "keep forever" is not the
 * safe default it looks like: the `subject` column holds an e-mail address.
 *
 * Reuses the `tx_scheduler_task.tx_form_retention_days` column that
 * CleanupValidationLogTask introduced, so this adds no schema.
 */
final class CleanupConsentLogTask extends AbstractTask
{
    private const DEFAULT_RETENTION_DAYS = 1095;

    protected int $txFormRetentionDays = self::DEFAULT_RETENTION_DAYS;

    public function execute(): bool
    {
        $retentionDays = max(1, $this->txFormRetentionDays);
        $cutoff = time() - $retentionDays * 86400;

        // Tasks are instantiated by the scheduler, not by the container, so the
        // repository cannot be constructor-injected here.
        $repository = GeneralUtility::makeInstance(ConsentLogRepository::class);
        $repository->deleteOlderThan($cutoff);
        // Order matters: a wording is orphaned only once the last log row
        // referring to it is gone.
        $repository->deleteOrphanedTexts();

        return true;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function setTaskParameters(array $parameters): void
    {
        $this->txFormRetentionDays = (int)($parameters['tx_form_retention_days'] ?? self::DEFAULT_RETENTION_DAYS);
    }

    public function getAdditionalInformation(): string
    {
        return sprintf('Retention: %d days', $this->txFormRetentionDays);
    }
}
