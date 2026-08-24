<?php

use TYPO3\CMS\Form\Controller\FormEditorController;
use TYPO3\CMS\Form\Controller\FormManagerController;
use TYPO3\CMS\Form\Controller\ConsentLogController;
use TYPO3\CMS\Form\Controller\MailLogController;
use TYPO3\CMS\Form\Controller\ValidationStatsController;

/**
 * Definitions for modules provided by EXT:form
 */
return [
    'web_FormFormbuilder' => [
        // v13 has no 'content' main module (that is v14's reorganised tree);
        // the form builder lives under 'web' here.
        'parent' => 'web',
        'position' => ['after' => 'workspaces_admin'],
        'access' => 'user',
        'path' => '/module/form',
        'iconIdentifier' => 'module-form',
        'labels' => 'form.module',
        'inheritNavigationComponentFromMainModule' => false,
    ],
    'form_manager' => [
        'parent' => 'web_FormFormbuilder',
        'access' => 'user',
        'path' => '/module/form/overview',
        'iconIdentifier' => 'module-form',
        'labels' => 'form.modules.form_manager',
        'extensionName' => 'Form',
        'controllerActions' => [
            FormManagerController::class => [
                'index', 'show', 'create', 'duplicate', 'references', 'delete',
            ],
        ],
    ],
    // WapplerSystems fork: the form log, in the administration section next to
    // the other operational logs rather than next to the form builder.
    //
    // Deliberately a separate icon: form_manager and form_editor both use
    // `module-form`, and a third identical entry would be unreadable.
    //
    // The controller carries a doc-header view switch, so the validation
    // statistics are one more entry in `controllerActions` rather than a second
    // module.
    //
    // Why NOT `parent => web_FormFormbuilder`, which looks like the natural
    // home: BackendModuleValidator remembers the last third-level module a user
    // opened and makes it the landing page of its second-level parent
    // (`$parentModuleData['action']`, unconditional and not opt-out-able). As a
    // child of the form builder, one visit to the log permanently turned "Forms"
    // in the module menu into the log — and since the module menu renders only
    // two levels, the form list was then left with no reachable entry point at
    // all. A monitoring view must not be able to displace the thing it monitors.
    // Keep this module on the second level.
    //
    // The path stays below /module/form so existing links and bookmarks keep
    // working; backend routes are flat and do not have to mirror the hierarchy.
    'form_log' => [
        // v13's equivalent of v14's 'admin' section is 'system', which is where
        // the core log module (system_log) sits.
        'parent' => 'system',
        'position' => ['after' => 'system_log'],
        'access' => 'user',
        'path' => '/module/form/log',
        'iconIdentifier' => 'actions-envelope',
        'labels' => 'form.modules.form_log',
        'extensionName' => 'Form',
        'controllerActions' => [
            MailLogController::class => [
                'index', 'show',
            ],
            ValidationStatsController::class => [
                'index',
            ],
            ConsentLogController::class => [
                'index', 'show',
            ],
        ],
    ],
    'form_editor' => [
        'parent' => 'web_FormFormbuilder',
        'access' => 'user',
        'path' => '/module/form/editor',
        'iconIdentifier' => 'module-form',
        'navigationComponent' => '@typo3/form/backend/form-editor-tree-container',
        'labels' => 'form.modules.form_editor',
        'extensionName' => 'Form',
        'controllerActions' => [
            FormEditorController::class => [
                'index', 'saveForm', 'renderFormPage', 'renderEmailPreview', 'sendTestEmail',
            ],
        ],
    ],
];
