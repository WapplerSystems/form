<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Form\WapplerSystems\Event\AfterYamlConfigurationLoadedEvent;

/**
 * Populates the EmailFinisher's `language` editor dropdown in the form
 * editor with the actual list of site languages present in this TYPO3
 * installation. Without this listener, integrators have to hand-maintain
 * the dropdown options in the prototype YAML.
 *
 * Listens to AfterYamlConfigurationLoadedEvent on every YAML load, so
 * adding a site language in the BE makes it immediately available in the
 * form editor without any cache flush.
 *
 * Promoted from wapplersystems/form_extended (AfterYamlConfigurationLoadedEventListener)
 * with added structural null-safety: the listener now no-ops cleanly when
 * the targeted YAML structure is absent (e.g. minimal prototypes).
 */
#[AsEventListener('wapplersystems-form/inject-site-languages-into-email-finisher-editor')]
final class InjectSiteLanguagesIntoEmailFinisherEditor
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    public function __invoke(AfterYamlConfigurationLoadedEvent $event): void
    {
        $yamlConfiguration = $event->yamlConfiguration;
        if (!is_array($yamlConfiguration['prototypes'] ?? null)) {
            return;
        }

        $options = [
            5 => [
                'value' => '',
                'label' => 'formEditor.elements.Form.finisher.Email.editor.language.option.frontend',
            ],
        ];
        $languages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getLanguages() as $language) {
                $code = $language->getLocale()->getLanguageCode();
                if (!isset($languages[$code])) {
                    $languages[$code] = $language->getTitle();
                }
            }
        }
        foreach ($languages as $locale => $title) {
            $options[] = [
                'value' => $locale,
                'label' => $title,
            ];
        }

        foreach ($yamlConfiguration['prototypes'] as $prototypeName => &$prototype) {
            $finisherEditors = &$prototype['formElementsDefinition']['Form']['formEditor']['propertyCollections']['finishers'] ?? null;
            if (!is_array($finisherEditors)) {
                continue;
            }
            foreach ($finisherEditors as &$finisher) {
                if (!is_array($finisher['editors'] ?? null)) {
                    continue;
                }
                foreach ($finisher['editors'] as &$editor) {
                    if (($editor['identifier'] ?? '') === 'language') {
                        $editor['selectOptions'] = $options;
                    }
                }
                unset($editor);
            }
            unset($finisher);
        }
        unset($prototype);

        $event->yamlConfiguration = $yamlConfiguration;
    }
}
