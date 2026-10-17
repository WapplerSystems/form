<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * Read-mostly TCA for persisted form submissions. Records are created by the
 * SaveSubmission finisher and managed through the "Form submissions" backend
 * module; manual creation is disabled. Field values are stored as JSON in
 * `content` and are shown read-only here — the module renders them decoded.
 */

return [
    'ctrl' => [
        'title' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission',
        'label' => 'form_label',
        'label_alt' => 'form_identifier',
        'default_sortby' => 'crdate DESC',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'hideTable' => true,
        'rootLevel' => 0,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'typeicon_classes' => [
            'default' => 'module-form',
        ],
        'searchFields' => 'form_identifier,form_label,content',
    ],
    'columns' => [
        'crdate' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.crdate',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'form_identifier' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.form_identifier',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'form_label' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.form_label',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'page_uid' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.page_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'language_uid' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.language_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'content' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.content',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'field_labels' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.field_labels',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'ip_hash' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.ip_hash',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'session_hash' => [
            'label' => 'LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:tx_form_submission.session_hash',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'crdate, form_label, form_identifier, page_uid, language_uid, content, field_labels, ip_hash, session_hash',
        ],
    ],
];
