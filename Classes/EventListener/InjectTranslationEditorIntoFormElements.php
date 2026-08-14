<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Form\Event\AfterYamlConfigurationLoadedEvent;

/**
 * Fallback: adds the per-site-language translation editor (Inspector-TranslationEditor)
 * to any form element / finisher that does not already declare one itself.
 *
 * The fork's own ~29 built-in elements (Configuration/Form/Base/FormElements/*.yaml)
 * and finishers (Form.yaml propertyCollections.finishers) declare "translations" (and
 * "translationsOverview" on the Form root) statically now, like any other editor - so
 * site packages can override or remove it per element. This listener only still fires
 * for element/finisher types that don't (third-party extensions' own form elements),
 * so every element type gets the editor without every extension having to know about it.
 *
 * availableLanguages is deliberately NOT injected into the editor config (anymore) -
 * the editor JS reads it once per page load from TYPO3.settings.FormEditor.availableLanguages
 * (see FormEditorController::collectNonDefaultSiteLanguages() and getAvailableLanguages()
 * in inspector-component.ts) instead, which is what allows "translations" to be a plain,
 * static YAML entry instead of something only this listener could produce.
 *
 * Feeds the save-time validation config (the editor's propertyPath becomes a known
 * multi-value prefix, see MultiValuePropertiesExtractor) for whichever elements it
 * still applies to.
 */
#[AsEventListener('wapplersystems-form/inject-translation-editor-into-form-elements')]
final class InjectTranslationEditorIntoFormElements
{
    private const EDITOR_INDEX = 9700;
    private const OVERVIEW_EDITOR_INDEX = 9710;

    public function __invoke(AfterYamlConfigurationLoadedEvent $event): void
    {
        $yamlConfiguration = $event->yamlConfiguration;
        if (!is_array($yamlConfiguration['prototypes'] ?? null)) {
            return;
        }

        foreach ($yamlConfiguration['prototypes'] as $prototypeName => $prototype) {
            if (!is_array($prototype['formElementsDefinition'] ?? null)) {
                continue;
            }
            foreach ($prototype['formElementsDefinition'] as $formElementType => $formElement) {
                $editors = $formElement['formEditor']['editors'] ?? null;
                if (is_array($editors) && !$this->hasTranslationEditor($editors)) {
                    $editors[self::EDITOR_INDEX] = [
                        'identifier' => 'translations',
                        'templateName' => 'Inspector-TranslationEditor',
                        'label' => 'formEditor.elements.FormElement.editor.translations.label',
                        'propertyPath' => 'renderingOptions.translation.overrides',
                    ];

                    // Form-wide translation overview on the Form (root) element only:
                    // a single matrix of every element × every language.
                    if ($formElementType === 'Form' && !$this->hasOverviewEditor($editors)) {
                        $editors[self::OVERVIEW_EDITOR_INDEX] = [
                            'identifier' => 'translationsOverview',
                            'templateName' => 'Inspector-TranslationOverviewEditor',
                            'label' => 'formEditor.elements.Form.editor.translationsOverview.label',
                        ];
                    }

                    // Plain key assignment appends at the end of the array regardless
                    // of the key's numeric value (PHP arrays keep insertion order) —
                    // re-sort so e.g. the static "remove element" editor at 9999 stays
                    // last instead of ending up before these injected ones.
                    $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['editors'] = ArrayUtility::sortArrayWithIntegerKeys($editors);
                }

                // Per-finisher translation editor (propertyPath: options.translation.overrides).
                // Finishers live under propertyCollections.finishers (only on the Form element);
                // the editor surfaces translatable string options (subject/message/plainMessage)
                // and the TranslationService overlay applies them at render time. Registering its
                // propertyPath as a collection multi-value prefix (see the PropertyCollectionElement
                // MultiValuePropertiesExtractor) keeps finishers.<n>.options.translation.overrides.*
                // safe on save.
                $finishers = $formElement['formEditor']['propertyCollections']['finishers'] ?? null;
                if (is_array($finishers)) {
                    foreach ($finishers as $finisherIndex => $finisher) {
                        $finisherEditors = $finisher['editors'] ?? null;
                        if (!is_array($finisherEditors) || $this->hasTranslationEditor($finisherEditors)) {
                            continue;
                        }
                        $finisherEditors[self::EDITOR_INDEX] = [
                            'identifier' => 'translations',
                            'templateName' => 'Inspector-TranslationEditor',
                            'label' => 'formEditor.elements.FormElement.editor.translations.label',
                            'propertyPath' => 'options.translation.overrides',
                        ];
                        $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['propertyCollections']['finishers'][$finisherIndex]['editors'] = ArrayUtility::sortArrayWithIntegerKeys($finisherEditors);
                    }
                }
            }
        }

        $event->yamlConfiguration = $yamlConfiguration;
    }

    /**
     * @param array<int|string, mixed> $editors
     */
    private function hasTranslationEditor(array $editors): bool
    {
        foreach ($editors as $editor) {
            if (is_array($editor) && ($editor['identifier'] ?? '') === 'translations') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int|string, mixed> $editors
     */
    private function hasOverviewEditor(array $editors): bool
    {
        foreach ($editors as $editor) {
            if (is_array($editor) && ($editor['identifier'] ?? '') === 'translationsOverview') {
                return true;
            }
        }
        return false;
    }
}
