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
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Prunes rows from `tx_form_mail_log` older than the configured retention
 * window. Companion to the RecordMailDeliveries listener.
 *
 * Needed for two separate reasons. The table grows with every submission, and —
 * unlike the validation log, which stores no user values at all — a row here can
 * carry a recipient address when its form opted in. Storage limitation
 * (Art. 5(1)(e) GDPR) is therefore not just housekeeping.
 *
 * Reuses the `tx_scheduler_task.tx_form_retention_days` column that
 * CleanupValidationLogTask already introduced, so this adds no schema. The
 * default is 90 days; for a log holding recipient addresses, consider less.
 *
 * Deletion is by age only. Notably it does NOT spare rows still sitting in a
 * non-terminal status: a row abandoned three months ago has told its story, and
 * the module derives "aborted" from age anyway rather than from a written flag.
 */
final class CleanupMailLogTask extends AbstractTask
{
    protected int $txFormRetentionDays = 90;

    public function execute(): bool
    {
        $retentionDays = max(1, $this->txFormRetentionDays);
        $cutoff = time() - $retentionDays * 86400;

        // Tasks are instantiated by the scheduler, not by the container, so the
        // repository cannot be constructor-injected here.
        GeneralUtility::makeInstance(MailLogRepository::class)->deleteOlderThan($cutoff);

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
