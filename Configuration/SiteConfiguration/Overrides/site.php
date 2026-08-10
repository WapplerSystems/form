<?php

declare(strict_types=1);

/*
 * Adds an inline `senders` collection to the `site` configuration when the
 * featureSiteEmail flag is enabled. Each entry is a site_sender row.
 * Editors maintain the list in the BE Site Configuration module; the form
 * plugin's FlexForm exposes a dropdown to pick one per content element.
 */

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$featureSiteEmail = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('form', 'featureSiteEmail');

if (!$featureSiteEmail) {
    return;
}

$GLOBALS['SiteConfiguration']['site']['columns']['senders'] = [
    'label' => 'Email senders',
    'description' => 'Senders selectable on the form plugin (when the site-sender feature is enabled).',
    'config' => [
        'type' => 'inline',
        'foreign_table' => 'site_sender',
        'appearance' => [
            'enabledControls' => [
                'info' => false,
            ],
        ],
    ],
];

$GLOBALS['SiteConfiguration']['site']['types'][0]['showitem'] .= ',--div--;Form senders,senders';
