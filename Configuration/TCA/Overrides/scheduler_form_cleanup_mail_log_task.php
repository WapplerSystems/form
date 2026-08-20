<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Form\Task\CleanupMailLogTask;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_scheduler_task'])) {
    return;
}

// The column is shared with CleanupValidationLogTask, which declares it in
// scheduler_form_cleanup_validation_log_task.php. TCA overrides load in
// alphabetical order, so THIS file runs first — declaring the column here as
// well (an identical definition merges to a no-op) avoids depending on that
// ordering, which is the kind of coupling that breaks the day a file is renamed.
ExtensionManagementUtility::addTCAcolumns(
    'tx_scheduler_task',
    [
        'tx_form_retention_days' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/Database.xlf:scheduler.retentionDays.label',
            'description' => 'LLL:EXT:form/Resources/Private/Language/Database.xlf:scheduler.retentionDays.description',
            'config' => [
                'type' => 'number',
                'default' => 90,
                'range' => [
                    'lower' => 1,
                    'upper' => 3650,
                ],
                'required' => true,
            ],
        ],
    ],
);

ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:form/Resources/Private/Language/Database.xlf:scheduler.cleanupMailLog.label',
        'description' => 'LLL:EXT:form/Resources/Private/Language/Database.xlf:scheduler.cleanupMailLog.description',
        'value' => CleanupMailLogTask::class,
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
