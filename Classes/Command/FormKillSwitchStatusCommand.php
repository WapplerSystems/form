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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Form\Service\FormKillSwitch;

/**
 * What is currently blocked, since when, by whom and until when.
 *
 * Exit code 1 when anything is blocked, so a monitoring job can watch for the
 * case this whole feature makes possible and nobody thinks about afterwards:
 * a form that was switched off during an incident and never switched back on.
 * That failure is silent by nature — the site looks fine, it just stopped
 * receiving enquiries.
 */
#[AsCommand(
    'form:status',
    'Show which forms are currently blocked. Exits 1 when anything is blocked.'
)]
final class FormKillSwitchStatusCommand extends Command
{
    public function __construct(
        private readonly FormKillSwitch $killSwitch,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $state = $this->killSwitch->getState();

        $rows = [];
        if ($state['global'] !== null) {
            $rows[] = $this->row('all forms', '', $state['global']);
        }
        foreach ($state['sites'] as $identifier => $entry) {
            $rows[] = $this->row('site', (string)$identifier, $entry);
        }
        foreach ($state['forms'] as $identifier => $entry) {
            $rows[] = $this->row('form', (string)$identifier, $entry);
        }

        if ($rows === []) {
            $io->success('Nothing is blocked.');
            $this->reportAutoDisable($io, $state['autoDisable']);
            return Command::SUCCESS;
        }

        $io->warning(count($rows) === 1 ? '1 block is active.' : count($rows) . ' blocks are active.');
        $io->table(['Level', 'Identifier', 'Since', 'Until', 'By', 'Message'], $rows);
        $this->reportAutoDisable($io, $state['autoDisable']);

        return Command::FAILURE;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function row(string $level, string $identifier, array $entry): array
    {
        $until = $entry['until'] ?? null;

        return [
            $level,
            $identifier,
            isset($entry['since']) ? date('Y-m-d H:i', (int)$entry['since']) : '',
            $until === null ? 'until lifted' : date('Y-m-d H:i', (int)$until),
            (string)($entry['by'] ?? ''),
            (string)($entry['message'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function reportAutoDisable(SymfonyStyle $io, array $settings): void
    {
        if (!$this->autoDisableEnabled()) {
            $io->writeln('Automatic shutdown: off (featureFormAutoDisable).');
            return;
        }

        $io->writeln(sprintf(
            'Automatic shutdown: on — %d rejected validations within %d minute(s) block a form for %d minute(s)%s.',
            (int)$settings['threshold'],
            (int)$settings['windowMinutes'],
            (int)$settings['durationMinutes'],
            ($settings['notify'] ?? '') !== '' ? ', notifying ' . $settings['notify'] : ', without notification'
        ));
    }

    private function autoDisableEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', 'featureFormAutoDisable');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }
    }
}
