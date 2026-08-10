<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Form\Task\CleanupSubmissionsTask;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_scheduler_task'])) {
    return;
}

// The `tx_form_retention_days` column is already registered by the validation
// log cleanup task override — it is reused here (identical semantics).

ExtensionManagementUtility::addRecordType(
    [
        'label' => 'Form: clean up submissions',
        'description' => 'Deletes rows from tx_form_submission and tx_form_webhook_log older than the configured retention window. Run daily.',
        'value' => CleanupSubmissionsTask::class,
        'icon' => 'mimetypes-x-tx_scheduler_task_group',
        'iconOverlay' => 'content-clock',
        'group' => 'form',
    ],
    '
    --div--;core.form.tabs:general,
        tasktype,
        task_group,
        description,
        tx_form_retention_days,
    --div--;core.form.tabs:timing,
        --palette--;;execution,
    --div--;core.form.tabs:access,
        disable,
    --div--;core.form.tabs:extended,',
    [],
    '',
    'tx_scheduler_task',
);
