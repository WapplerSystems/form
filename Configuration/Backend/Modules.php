<?php

use TYPO3\CMS\Form\Controller\FormEditorController;
use TYPO3\CMS\Form\Controller\FormManagerController;
use TYPO3\CMS\Form\Controller\MailLogController;
use TYPO3\CMS\Form\Controller\ValidationStatsController;

/**
 * Definitions for modules provided by EXT:form
 */
return [
    'web_FormFormbuilder' => [
        'parent' => 'content',
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
    // WapplerSystems fork: the form log. Sibling of the form builder, not a child
    // of it — see the note below on why that distinction is load-bearing.
    //
    // Deliberately a separate icon: form_manager and form_editor both use
    // `module-form`, and a third identical entry would be unreadable.
    //
    // The controller carries a doc-header view switch, so the validation
    // statistics are one more entry in `controllerActions` rather than a second
    // module.
    //
    // Why not `parent => web_FormFormbuilder`: BackendModuleValidator remembers
    // the last third-level module a user opened and makes it the landing page of
    // its second-level parent (`$parentModuleData['action']`, unconditional and
    // not opt-out-able). As a child of the form builder, one visit to the log
    // permanently turned "Forms" in the module menu into the log — and since the
    // module menu only renders two levels, the form list then had no reachable
    // entry point at all. A monitoring view must not be able to displace the
    // thing it monitors.
    //
    // The path stays below /module/form so existing links and bookmarks keep
    // working; backend routes are flat and do not have to mirror the hierarchy.
    'form_log' => [
        'parent' => 'content',
        'position' => ['after' => 'web_FormFormbuilder'],
        'access' => 'user',
        'inheritNavigationComponentFromMainModule' => false,
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
