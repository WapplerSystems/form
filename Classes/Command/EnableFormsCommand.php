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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lifts a block set by form:disable or by the automatic shutdown.
 *
 * Note that the levels are independent: lifting the block on one form does not
 * help while the whole site or the installation is still blocked, because any
 * level that is set blocks. `form:status` shows all three.
 */
#[AsCommand(
    'form:enable',
    'Lift the block on a form, a site or the whole installation.'
)]
final class EnableFormsCommand extends AbstractKillSwitchCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'form',
                InputArgument::OPTIONAL,
                'Form identifier to unblock. Omit it to unblock a whole site (--site) or the entire installation.'
            )
            ->addOption(
                'site',
                's',
                InputOption::VALUE_REQUIRED,
                'Site identifier to unblock. Ignored when a form identifier is given.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $this->resolveScope($input);

        $this->killSwitch->enable($target['scope'], $target['identifier']);

        $io->success('Unblocked ' . $target['label'] . '.');

        $state = $this->killSwitch->getState();
        if ($state['global'] !== null && $target['scope'] !== 'global') {
            $io->warning('Every form is still blocked globally. Run "form:enable" without arguments to lift that.');
        }

        return Command::SUCCESS;
    }
}
