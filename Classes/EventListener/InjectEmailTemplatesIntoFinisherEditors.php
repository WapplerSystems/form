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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Event\AfterYamlConfigurationLoadedEvent;

/**
 * Feeds the email content editor (Inspector-EmailContentEditor, Feature 3) with the
 * list of available Fluid email templates so the editor can offer a template chooser.
 *
 * The list is derived dynamically by scanning the finisher's configured
 * `options.templateRootPaths` for `*.fluid.html` files — so templates added by
 * integrators via templateRootPaths automatically appear in the chooser without any
 * code change. Injected centrally via AfterYamlConfigurationLoadedEvent (same channel
 * as the variants editor) so it also feeds the form-editor setup delivered to the JS.
 */
#[AsEventListener('wapplersystems-form/inject-email-templates-into-finisher-editors')]
final class InjectEmailTemplatesIntoFinisherEditors
{
    public function __invoke(AfterYamlConfigurationLoadedEvent $event): void
    {
        $yamlConfiguration = $event->yamlConfiguration;
        if (!is_array($yamlConfiguration['prototypes'] ?? null)) {
            return;
        }

        foreach ($yamlConfiguration['prototypes'] as $prototypeName => $prototype) {
            $finishersDefinition = $prototype['finishersDefinition'] ?? null;
            $formElements = $prototype['formElementsDefinition'] ?? null;
            if (!is_array($formElements)) {
                continue;
            }

            foreach ($formElements as $formElementType => $formElement) {
                $finisherEditorCollections = $formElement['formEditor']['propertyCollections']['finishers'] ?? null;
                if (!is_array($finisherEditorCollections)) {
                    continue;
                }

                foreach ($finisherEditorCollections as $collectionIndex => $finisher) {
                    $finisherIdentifier = $finisher['identifier'] ?? null;
                    $editors = $finisher['editors'] ?? null;
                    if (!is_string($finisherIdentifier) || !is_array($editors)) {
                        continue;
                    }

                    $templateRootPaths = $finishersDefinition[$finisherIdentifier]['options']['templateRootPaths'] ?? [];
                    $availableTemplates = $this->scanTemplates(is_array($templateRootPaths) ? $templateRootPaths : []);

                    foreach ($editors as $editorIndex => $editor) {
                        if (!is_array($editor) || ($editor['templateName'] ?? '') !== 'Inspector-EmailContentEditor') {
                            continue;
                        }
                        $yamlConfiguration['prototypes'][$prototypeName]['formElementsDefinition'][$formElementType]['formEditor']['propertyCollections']['finishers'][$collectionIndex]['editors'][$editorIndex]['availableTemplates'] = $availableTemplates;
                    }
                }
            }
        }

        $event->yamlConfiguration = $yamlConfiguration;
    }

    /**
     * Scan the given template root paths for Fluid email templates (*.fluid.html)
     * and return a map of {templateName => label}. The template name is the file
     * name without the ".fluid.html" suffix (e.g. "Default"); later root paths
     * override earlier ones (highest array key wins), mirroring TYPO3 root-path
     * fallback semantics.
     *
     * @param array<int|string, mixed> $templateRootPaths
     * @return array<string, string>
     */
    private function scanTemplates(array $templateRootPaths): array
    {
        $templates = [];
        ksort($templateRootPaths);

        foreach ($templateRootPaths as $rootPath) {
            if (!is_string($rootPath) || $rootPath === '') {
                continue;
            }
            $absolutePath = GeneralUtility::getFileAbsFileName($rootPath);
            if ($absolutePath === '' || !is_dir($absolutePath)) {
                continue;
            }
            $files = GeneralUtility::getFilesInDir($absolutePath, 'html');
            foreach ($files as $file) {
                if (!str_ends_with($file, '.fluid.html')) {
                    continue;
                }
                $templateName = substr($file, 0, -strlen('.fluid.html'));
                if ($templateName !== '') {
                    $templates[$templateName] = $templateName;
                }
            }
        }

        // Guarantee at least the runtime default is selectable.
        if ($templates === []) {
            $templates['Default'] = 'Default';
        }

        return $templates;
    }
}
