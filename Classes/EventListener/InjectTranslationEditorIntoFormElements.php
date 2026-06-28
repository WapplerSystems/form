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
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Form\Event\AfterYamlConfigurationLoadedEvent;

/**
 * Adds the per-site-language translation editor (Inspector-TranslationEditor) to every
 * form element in the form editor, so editors can translate labels, placeholders and
 * options into all configured site languages directly in the backend — stored in the
 * form definition under renderingOptions.translation.overrides.<languageCode> and
 * applied at render time by the fork's TranslationService overlay.
 *
 * The list of non-default site languages is injected into the editor config
 * (availableLanguages) so the modal can render one section per language. Injecting
 * centrally via AfterYamlConfigurationLoadedEvent covers all element types incl.
 * custom ones and feeds the save-time validation config (the editor's propertyPath
 * becomes a known multi-value prefix, see MultiValuePropertiesExtractor).
 */
#[AsEventListener('wapplersystems-form/inject-translation-editor-into-form-elements')]
final class InjectTranslationEditorIntoFormElements
{
    private const EDITOR_INDEX = 9700;
    private const OVERVIEW_EDITOR_INDEX = 9710;

    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    public function __invoke(AfterYamlConfigurationLoadedEvent $event): void
    {
        $yamlConfiguration = $event->yamlConfiguration;
        if (!is_array($yamlConfiguration['prototypes'] ?? null)) {
            return;
        }

        $availableLanguages = $this->collectNonDefaultLanguages();
        if ($availableLanguages === []) {
            // Nothing to translate to — don't add the editor at all.
            return;
        }

        foreach ($yamlConfiguration['prototypes'] as $prototypeName => $prototype) {
            if (!is_array($prototype['formElementsDefinition'] ?? null)) {
                continue;
            }
            foreach ($prototype['formElementsDefinition'] as $formElementType => $formElement) {
                $editors = $formElement['formEditor']['editors'] ?? null;
                if (is_array($editors) && !$this->hasTranslationEditor($editors)) {
                    $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['editors'][self::EDITOR_INDEX] = [
                        'identifier' => 'translations',
                        'templateName' => 'Inspector-TranslationEditor',
                        'label' => 'formEditor.elements.FormElement.editor.translations.label',
                        'propertyPath' => 'renderingOptions.translation.overrides',
                        'availableLanguages' => $availableLanguages,
                    ];

                    // Form-wide translation overview on the Form (root) element only:
                    // a single matrix of every element × every language.
                    if ($formElementType === 'Form' && !$this->hasOverviewEditor($editors)) {
                        $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['editors'][self::OVERVIEW_EDITOR_INDEX] = [
                            'identifier' => 'translationsOverview',
                            'templateName' => 'Inspector-TranslationOverviewEditor',
                            'label' => 'formEditor.elements.Form.editor.translationsOverview.label',
                            'availableLanguages' => $availableLanguages,
                        ];
                    }
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
                        $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['propertyCollections']['finishers'][$finisherIndex]['editors'][self::EDITOR_INDEX] = [
                            'identifier' => 'translations',
                            'templateName' => 'Inspector-TranslationEditor',
                            'label' => 'formEditor.elements.FormElement.editor.translations.label',
                            'propertyPath' => 'options.translation.overrides',
                            'availableLanguages' => $availableLanguages,
                        ];
                    }
                }
            }
        }

        $event->yamlConfiguration = $yamlConfiguration;
    }

    /**
     * @return array<int, array{code: string, title: string}>
     */
    private function collectNonDefaultLanguages(): array
    {
        $languages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                if ($language->getLanguageId() === 0) {
                    continue;
                }
                $code = $language->getLocale()->getLanguageCode();
                if ($code === '' || isset($languages[$code])) {
                    continue;
                }
                $languages[$code] = ['code' => $code, 'title' => $language->getTitle()];
            }
        }
        return array_values($languages);
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
