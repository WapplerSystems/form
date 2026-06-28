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

use TYPO3\CMS\Backend\Configuration\SiteTcaConfiguration;
use TYPO3\CMS\Core\Configuration\Processor\Placeholder\EnvPlaceholderProcessor;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Decorator for the core SiteDatabaseEditRow FormDataProvider that loads
 * existing `site_sender` rows from the site configuration when the BE
 * Site Configuration module renders the edit form for an inline child.
 * Registered as a Symfony DI decorator in Configuration/Services.yaml.
 *
 * For all other tableNames the parent class handles the request. Keep
 * the `site_sender` branch in sync with upstream's per-table loading
 * pattern (mirrored from the parent's logic for site_errorhandling
 * et al). The constructor re-declares the dependencies because the
 * parent class made them `private` — the singletons resolved by the
 * container are the same instances, so there is no extra cost.
 */
final readonly class SiteDatabaseEditRow extends \TYPO3\CMS\Backend\Form\FormDataProvider\SiteDatabaseEditRow
{
    public function __construct(
        SiteFinder $siteFinder,
        SiteTcaConfiguration $siteTcaConfiguration,
        EnvPlaceholderProcessor $envPlaceholderProcessor,
        private EnvPlaceholderProcessor $childEnvPlaceholderProcessor,
    ) {
        parent::__construct($siteFinder, $siteTcaConfiguration, $envPlaceholderProcessor);
    }

    public function addData(array $result): array
    {
        if ($result['command'] !== 'edit' || !empty($result['databaseRow'])) {
            return $result;
        }
        if ($result['tableName'] !== 'site_sender') {
            return parent::addData($result);
        }

        $unprocessedRootPageId = $result['inlineTopMostParentUid'] ?? $result['inlineParentUid'];
        $processedRootPageId = $this->childEnvPlaceholderProcessor->canProcess($unprocessedRootPageId)
            ? (int)$this->childEnvPlaceholderProcessor->process($unprocessedRootPageId)
            : (int)$unprocessedRootPageId;

        try {
            $rowData = $this->getRawConfigurationForSiteWithRootPageId($processedRootPageId);
            $parentFieldName = $result['inlineParentFieldName'];
            if (!isset($rowData[$parentFieldName])) {
                throw new \RuntimeException('Field "' . $parentFieldName . '" not found', 1520886092);
            }
            $rowData = $rowData[$parentFieldName][$result['vanillaUid']];
            $result['databaseRow']['uid'] = $result['vanillaUid'];
        } catch (SiteNotFoundException) {
            $rowData = [];
        }

        foreach ($rowData as $fieldName => $value) {
            if (!is_array($value)) {
                $result['databaseRow'][$fieldName] = $value;
            }
        }
        $result['databaseRow']['pid'] = 0;
        return $result;
    }
}
