<?php

/*
 * release/v13: the backend scripts are shared with release/v14 and import a
 * few things 13.4 does not have. The entries below the fork's own prefix fill
 * those gaps; see Resources/Public/JavaScript/compat/.
 */
return [
    'dependencies' => [
        'backend',
        'core',
    ],
    'tags' => [
        // The form editor's tree is the module's navigation component and is
        // imported in the top frame. 13.4 only puts tagged packages into that
        // frame's import map (BackendController::includeTaggedImports()).
        'backend.navigation-component',
    ],
    'imports' => [
        '@typo3/form/backend/' => 'EXT:form/Resources/Public/JavaScript/backend/',
        '@typo3/form/compat/' => 'EXT:form/Resources/Public/JavaScript/compat/',

        // v14 virtual label modules -> shims that load the domain via AJAX.
        '~labels/form.form_manager_javascript' => 'EXT:form/Resources/Public/JavaScript/compat/labels/form.form_manager_javascript.js',
        '~labels/form.form_editor_javascript' => 'EXT:form/Resources/Public/JavaScript/compat/labels/form.form_editor_javascript.js',
        '~labels/form.form_editor_fail_safe_error_handling_javascript' => 'EXT:form/Resources/Public/JavaScript/compat/labels/form.form_editor_fail_safe_error_handling_javascript.js',
        '~labels/form.relative_date_editor' => 'EXT:form/Resources/Public/JavaScript/compat/labels/form.relative_date_editor.js',
        '~labels/backend.wizards.general' => 'EXT:form/Resources/Public/JavaScript/compat/labels/backend.wizards.general.js',

        // v14's generic backend wizard (used by the new/duplicate form wizard),
        // copied unchanged from TYPO3 v14.3.11. 13.4 ships neither file nor
        // folder, so these entries shadow nothing from EXT:backend.
        '@typo3/backend/wizard/wizard.js' => 'EXT:form/Resources/Public/JavaScript/compat/backend/wizard/wizard.js',
        '@typo3/backend/wizard/events/' => 'EXT:form/Resources/Public/JavaScript/compat/backend/wizard/events/',
        '@typo3/backend/wizard/steps/' => 'EXT:form/Resources/Public/JavaScript/compat/backend/wizard/steps/',
        // Finishers are imported dynamically by name (finisher-step.js).
        '@typo3/backend/wizard/finisher/' => 'EXT:form/Resources/Public/JavaScript/compat/backend/wizard/finisher/',
        '@typo3/core/utility/top-window.js' => 'EXT:form/Resources/Public/JavaScript/compat/core/utility/top-window.js',
    ],
];
