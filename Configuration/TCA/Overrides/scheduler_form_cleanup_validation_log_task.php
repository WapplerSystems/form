<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Form\Task\CleanupValidationLogTask;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_scheduler_task'])) {
    return;
}

ExtensionManagementUtility::addTCAcolumns(
    'tx_scheduler_task',
    [
        'tx_form_retention_days' => [
            'label' => 'Retention window (days)',
            'description' => 'Rows older than this number of days are deleted on each run. Recommended 7–365.',
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
        'label' => 'Form: clean up validation log',
        'description' => 'Deletes rows from tx_form_validation_log older than the configured retention window. Run daily.',
        'value' => CleanupValidationLogTask::class,
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
