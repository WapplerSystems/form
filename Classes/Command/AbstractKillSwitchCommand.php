<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use TYPO3\CMS\Form\Service\FormKillSwitch;

/**
 * Shared scaffolding for the kill-switch commands.
 *
 * The command line is not a convenience here, it is the primary interface for
 * the case the feature exists for. A flood does not wait for someone to find a
 * backend login on their phone, and an SSH session is the one way in that still
 * works when the site is being hammered.
 *
 * @internal not part of public TYPO3 Core API
 */
abstract class AbstractKillSwitchCommand extends Command
{
    public function __construct(
        protected readonly FormKillSwitch $killSwitch,
    ) {
        parent::__construct();
    }

    /**
     * Which level the arguments address. A form identifier is the most specific
     * and therefore wins over `--site`; naming neither means the whole
     * installation, which is why that case has to be spelled out by omission
     * rather than by a `--all` flag one could pass by accident.
     *
     * @return array{scope: string, identifier: string|null, label: string}
     */
    protected function resolveScope(InputInterface $input): array
    {
        $form = trim((string)$input->getArgument('form'));
        if ($form !== '') {
            return ['scope' => FormKillSwitch::SCOPE_FORM, 'identifier' => $form, 'label' => 'form "' . $form . '"'];
        }

        $site = trim((string)$input->getOption('site'));
        if ($site !== '') {
            return ['scope' => FormKillSwitch::SCOPE_SITE, 'identifier' => $site, 'label' => 'site "' . $site . '"'];
        }

        return ['scope' => FormKillSwitch::SCOPE_GLOBAL, 'identifier' => null, 'label' => 'all forms'];
    }

    /**
     * "2h", "90m", "1d", or a plain number of minutes. Returns null for an
     * open-ended block and throws on anything it cannot read — silently
     * treating a typo as "forever" is the wrong way to fail for an option whose
     * whole point is that the block ends.
     */
    protected function parseDuration(?string $duration): ?int
    {
        $duration = trim((string)$duration);
        if ($duration === '') {
            return null;
        }

        if (!preg_match('/^(\d+)\s*([mhd]?)$/i', $duration, $matches)) {
            throw new \InvalidArgumentException(
                'Cannot read the duration "' . $duration . '". Use e.g. 90m, 2h, 1d or a plain number of minutes.',
                1757890002
            );
        }

        $amount = (int)$matches[1];
        if ($amount < 1) {
            throw new \InvalidArgumentException('The duration must be at least one minute.', 1757890003);
        }

        $minutes = match (strtolower($matches[2])) {
            'h' => $amount * 60,
            'd' => $amount * 1440,
            default => $amount,
        };

        return (int)($GLOBALS['EXEC_TIME'] ?? time()) + $minutes * 60;
    }
}
