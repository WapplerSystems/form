<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Form\Task\CleanupConsentLogTask;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_scheduler_task'])) {
    return;
}

// The tx_form_retention_days column itself is added by the validation-log
// override; this file only registers a second task type that reuses it.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'Form: clean up consent log',
        'description' => 'Deletes rows from tx_form_consent_log older than the configured retention window, '
            . 'then drops the consent wordings nothing refers to any more. Run daily. '
            . 'NOTE: this table is evidence of consent under Art. 7(1) GDPR - pick the retention window from '
            . 'the purpose the consent was given for, not from the 90 days the monitoring logs use. Default 1095 (3 years).',
        'value' => CleanupConsentLogTask::class,
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
