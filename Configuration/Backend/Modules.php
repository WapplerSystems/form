<?php

use TYPO3\CMS\Form\Controller\FormEditorController;
use TYPO3\CMS\Form\Controller\FormManagerController;
use TYPO3\CMS\Form\Controller\MailLogController;

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
    // WapplerSystems fork: outgoing-mail log. A deliberately separate icon —
    // form_manager and form_editor both use `module-form`, and a third identical
    // entry in the module menu would be unusable.
    //
    // The controller carries a doc-header view switch so the planned
    // validation-statistics view becomes one more entry in `controllerActions`
    // rather than a second module.
    'form_log' => [
        'parent' => 'web_FormFormbuilder',
        'access' => 'user',
        'path' => '/module/form/log',
        'iconIdentifier' => 'actions-envelope',
        'labels' => 'form.modules.form_log',
        'extensionName' => 'Form',
        'controllerActions' => [
            MailLogController::class => [
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
