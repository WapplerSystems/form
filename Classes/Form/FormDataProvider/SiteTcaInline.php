<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Form\FormDataProvider;

/**
 * Decorator for the core SiteTcaInline FormDataProvider that adds the
 * `site_sender` inline child table to the allowlist. Registered as a
 * Symfony DI decorator in Configuration/Services.yaml.
 *
 * The core class re-implements `addData()` rather than exposing the
 * allowlist as configurable data, so we override the full method here.
 * Keep this class in lockstep with upstream `SiteTcaInline::addData()`
 * — if upstream changes, mirror the change and re-add `'site_sender'`
 * to the in_array() list.
 */
final class SiteTcaInline extends \TYPO3\CMS\Backend\Form\FormDataProvider\SiteTcaInline
{
    public function addData(array $result): array
    {
        $result = $this->addInlineFirstPid($result);
        foreach ($result['processedTca']['columns'] as $fieldName => $fieldConfig) {
            if (!$this->isInlineField($fieldConfig)) {
                continue;
            }
            $childTableName = $fieldConfig['config']['foreign_table'] ?? '';
            if (!in_array($childTableName, ['site_errorhandling', 'site_route', 'site_base_variant', 'site_sender'], true)) {
                throw new \RuntimeException('Inline relation to other tables not implemented', 1522494737);
            }
            $result['processedTca']['columns'][$fieldName]['children'] = [];
            $result = $this->resolveSiteRelatedChildren($result, $fieldName);
            if (!empty($result['processedTca']['columns'][$fieldName]['config']['selectorOrUniqueConfiguration'])) {
                throw new \RuntimeException('selectorOrUniqueConfiguration not implemented in sites module', 1624313533);
            }
        }
        return $result;
    }
}
