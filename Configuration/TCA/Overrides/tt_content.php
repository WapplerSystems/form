<?php

defined('TYPO3') or die();

call_user_func(static function () {
    // Register the plugin
    $contentTypeName = \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'Form',
        'Formframework',
        'LLL:EXT:form/Resources/Private/Language/locallang.xlf:form_new_wizard_title',
        'content-form',
        'forms',
        'LLL:EXT:form/Resources/Private/Language/locallang.xlf:form_new_wizard_description',
    );

    // v13's registerPlugin() has no FlexForm parameter (that is v14), so the data
    // structure and its tab are registered separately.
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:form/Configuration/FlexForms/FormFramework.xml',
        $contentTypeName
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:plugin, pi_flexform',
        $contentTypeName,
        'after:palette:headers'
    );

    $GLOBALS['TCA']['tt_content']['types'][$contentTypeName]['previewRenderer'] = \TYPO3\CMS\Form\Hooks\FormPagePreviewRenderer::class;
});
