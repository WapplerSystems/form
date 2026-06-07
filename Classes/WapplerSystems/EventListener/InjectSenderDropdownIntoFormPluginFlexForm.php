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

use Symfony\Component\DependencyInjection\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Form\WapplerSystems\Hooks\SiteSenderItemsProcFunc;

/**
 * Injects a `settings.sender` select field into the form plugin's FlexForm.
 * The dropdown items are populated at render time by SiteSenderItemsProcFunc
 * from the current site's `senders` attribute.
 *
 * Fires only for the form plugin's data structure
 * (`ext-form-persistenceIdentifier` set) and only when the `featureSiteEmail`
 * flag is enabled.
 *
 * Promoted from wapplersystems/form_extended.
 */
#[AsEventListener('wapplersystems-form/inject-sender-dropdown-into-form-plugin-flex-form')]
final class InjectSenderDropdownIntoFormPluginFlexForm
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function __invoke(AfterFlexFormDataStructureParsedEvent $event): void
    {
        $identifier = $event->getIdentifier();
        if (!isset($identifier['ext-form-persistenceIdentifier'])) {
            return;
        }
        if (!(bool)$this->extensionConfiguration->get('form', 'featureSiteEmail')) {
            return;
        }

        $dataStructure = $event->getDataStructure();
        $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.sender'] = [
            'label' => 'Email sender (site)',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [],
                'itemsProcFunc' => SiteSenderItemsProcFunc::class . '->getSiteSenders',
            ],
        ];
        $event->setDataStructure($dataStructure);
    }
}
