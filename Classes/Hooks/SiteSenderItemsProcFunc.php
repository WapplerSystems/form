<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Hooks;

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * itemsProcFunc for the `settings.sender` FlexForm select. Populates the
 * dropdown from the current site's `senders` attribute (maintained in the
 * BE Site Configuration module when the featureSiteEmail flag is on).
 *
 * Each item is rendered as "Display name <email@…>" with the email value
 * as the stored item value, so the runtime listener can look it up.
 */
final class SiteSenderItemsProcFunc
{
    /**
     * @param array{site?: Site, items?: array<array{0: string, 1: string}>} $config
     */
    public function getSiteSenders(array &$config): void
    {
        $site = $config['site'] ?? null;
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
            $email = (string)($sender['email'] ?? '');
            $name = (string)($sender['name'] ?? '');
            if ($email === '') {
                continue;
            }
            $label = $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
            $config['items'][] = [$label, $email];
        }
    }
}
