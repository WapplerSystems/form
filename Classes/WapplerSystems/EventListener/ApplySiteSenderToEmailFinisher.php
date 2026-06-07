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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Form\Event\BeforeEmailFinisherInitializedEvent;

/**
 * When the `featureSiteEmail` flag is enabled, override the EmailFinisher's
 * `senderAddress` / `senderName` options with the site_sender selected on
 * the form plugin's FlexForm (`settings.sender` field). This lets editors
 * pick a sender per content element instead of hard-coding it in the
 * form YAML.
 *
 * Architecturally cleaner than the form_extended approach which subclassed
 * EmailFinisher and re-implemented executeInternal(): here we only mutate
 * options via the standard BeforeEmailFinisherInitializedEvent, so future
 * changes to EmailFinisher don't ripple into this code.
 *
 * Promoted from wapplersystems/form_extended as part of the site-sender
 * feature migration. Listener no-ops when the feature flag is off, so
 * keeping the listener registered unconditionally has zero cost.
 */
#[AsEventListener('wapplersystems-form/apply-site-sender-to-email-finisher')]
final class ApplySiteSenderToEmailFinisher
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FlexFormService $flexFormService,
    ) {}

    public function __invoke(BeforeEmailFinisherInitializedEvent $event): void
    {
        if (!(bool)$this->extensionConfiguration->get('form', 'featureSiteEmail')) {
            return;
        }

        $request = $event->getFinisherContext()->getRequest();
        $contentObject = $request->getAttribute('currentContentObject');
        if ($contentObject === null) {
            return;
        }
        $flexform = $contentObject->data['pi_flexform'] ?? '';
        if (!is_string($flexform) || $flexform === '') {
            return;
        }

        $settings = $this->flexFormService->convertFlexFormContentToArray($flexform);
        $selectedEmail = $settings['settings']['sender'] ?? '';
        if (!is_string($selectedEmail) || $selectedEmail === '') {
            return;
        }

        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return;
        }
        $senders = $site->getAttribute('senders');
        if (!is_array($senders)) {
            return;
        }

        foreach ($senders as $sender) {
            if (!is_array($sender)) {
                continue;
            }
            if (($sender['email'] ?? '') !== $selectedEmail) {
                continue;
            }
            $options = $event->getOptions();
            $options['senderAddress'] = $selectedEmail;
            $options['senderName'] = (string)($sender['name'] ?? '');
            $event->setOptions($options);
            return;
        }
    }
}
