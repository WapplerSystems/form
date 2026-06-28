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
use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * When `featureSiteEmail` is on, the EmailToReceiver finisher's
 * `senderAddress` / `senderName` FlexForm fields become irrelevant —
 * the actual sender is resolved from the site_sender selection.
 * Setting those FlexForm fields to type=hidden prevents editors from
 * being misled into thinking they need to fill them in.
 *
 * Fires only when the event's `ext-form-overrideFinishers` identifier
 * is set (the finisher-override sheet in the form plugin) and only
 * when the feature flag is enabled.
 *
 * Promoted from wapplersystems/form_extended.
 */
#[AsEventListener('wapplersystems-form/hide-static-sender-fields-in-form-plugin-flex-form')]
final class HideStaticSenderFieldsInFormPluginFlexForm
{
    /** @var list<string> */
    private const FIELDS_TO_HIDE = [
        'settings.finishers.EmailToReceiver.senderAddress',
        'settings.finishers.EmailToReceiver.senderName',
    ];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function __invoke(AfterFlexFormDataStructureParsedEvent $event): void
    {
        if (!(bool)$this->extensionConfiguration->get('form', 'featureSiteEmail')) {
            return;
        }
        $identifier = $event->getIdentifier();
        if (($identifier['ext-form-overrideFinishers'] ?? '') === '') {
            return;
        }

        $dataStructure = $event->getDataStructure();
        foreach ($dataStructure['sheets'] ?? [] as $sheetName => $sheet) {
            $elements = $dataStructure['sheets'][$sheetName]['ROOT']['el'] ?? null;
            if (!is_array($elements)) {
                continue;
            }
            foreach ($elements as $key => $fieldConfig) {
                if (!is_array($fieldConfig) || !in_array($key, self::FIELDS_TO_HIDE, true)) {
                    continue;
                }
                $fieldConfig['config']['type'] = 'hidden';
                $dataStructure['sheets'][$sheetName]['ROOT']['el'][$key] = $fieldConfig;
            }
        }
        $event->setDataStructure($dataStructure);
    }
}
