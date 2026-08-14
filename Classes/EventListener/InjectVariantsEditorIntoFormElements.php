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
 * Fallback: adds the visual "Variants" editor (Inspector-VariantsEditor) to any
 * form element / finisher that does not already declare one itself.
 *
 * The fork's own ~29 built-in elements (Configuration/Form/Base/FormElements/*.yaml)
 * and finishers (Form.yaml propertyCollections.finishers) declare "variants"
 * statically now, like any other editor - so site packages can override or
 * remove it per element. This listener only still fires for element/finisher
 * types that don't (third-party extensions' own form elements), so every
 * element type gets the editor without every extension having to know about it.
 *
 * Because this event also feeds the save-time validation config
 * (ConfigurationService::getPrototypeConfiguration), the injected editor makes
 * the "variants" property path a known multi-value property
 * (see MultiValuePropertiesExtractor), which is what lets hand- or editor-authored
 * variants survive a form-editor save (round-trip) instead of being rejected.
 *
 * Only element types that already expose a `formEditor.editors` list (i.e. are
 * editable in the form editor) receive the editor, and only if they don't already
 * define one with identifier "variants".
 */
#[AsEventListener('wapplersystems-form/inject-variants-editor-into-form-elements')]
final class InjectVariantsEditorIntoFormElements
{
    /**
     * Sort index for the injected editor: high, so it appears near the bottom of
     * the inspector, but below the conventional remove button (9999).
     */
    private const EDITOR_INDEX = 9800;

    public function __invoke(AfterYamlConfigurationLoadedEvent $event): void
    {
        $yamlConfiguration = $event->yamlConfiguration;
        if (!is_array($yamlConfiguration['prototypes'] ?? null)) {
            return;
        }

        // Write by explicit array path (no reference + null-coalesce, which does
        // not reliably write back when appending a new key).
        foreach ($yamlConfiguration['prototypes'] as $prototypeName => $prototype) {
            if (!is_array($prototype['formElementsDefinition'] ?? null)) {
                continue;
            }
            foreach ($prototype['formElementsDefinition'] as $formElementType => $formElement) {
                // (a) Element-level variants editor (propertyPath: variants).
                $editors = $formElement['formEditor']['editors'] ?? null;
                if (is_array($editors) && !$this->hasVariantsEditor($editors)) {
                    $editors[self::EDITOR_INDEX] = [
                        'identifier' => 'variants',
                        'templateName' => 'Inspector-VariantsEditor',
                        'label' => 'formEditor.elements.FormElement.editor.variants.label',
                        'propertyPath' => 'variants',
                    ];
                    // Plain key assignment appends at the end of the array regardless
                    // of the key's numeric value (PHP arrays keep insertion order) —
                    // re-sort so e.g. the static "remove element" editor at 9999 stays
                    // last instead of ending up before this injected one.
                    $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['editors'] = ArrayUtility::sortArrayWithIntegerKeys($editors);
                }

                // (b) Finisher-level variants editor (propertyPath: options.variants).
                // Finishers live under propertyCollections.finishers (only on the
                // Form type, but iterated generically). Runtime support:
                // FormRuntime::processFinisherVariants() reads finisher.options.variants.
                $finishers = $formElement['formEditor']['propertyCollections']['finishers'] ?? null;
                if (is_array($finishers)) {
                    foreach ($finishers as $finisherIndex => $finisher) {
                        $finisherEditors = $finisher['editors'] ?? null;
                        if (!is_array($finisherEditors) || $this->hasVariantsEditor($finisherEditors)) {
                            continue;
                        }
                        $finisherEditors[self::EDITOR_INDEX] = [
                            'identifier' => 'variants',
                            'templateName' => 'Inspector-VariantsEditor',
                            'label' => 'formEditor.elements.FormElement.editor.variants.label',
                            'propertyPath' => 'options.variants',
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
    private function hasVariantsEditor(array $editors): bool
    {
        foreach ($editors as $editor) {
            if (is_array($editor) && ($editor['identifier'] ?? '') === 'variants') {
                return true;
            }
        }
        return false;
    }
}
