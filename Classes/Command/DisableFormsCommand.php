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
 * Stops forms from rendering and from accepting anything.
 *
 * Usage:
 *   # one form, until somebody turns it back on
 *   bin/typo3 form:disable contact
 *
 *   # one form for two hours, with a wording of your own
 *   bin/typo3 form:disable contact --for=2h --message="Wir sind gleich wieder da."
 *
 *   # every form of one site
 *   bin/typo3 form:disable --site=camino --for=30m
 *
 *   # everything (no form, no --site)
 *   bin/typo3 form:disable
 */
#[AsCommand(
    'form:disable',
    'Stop a form, a site or every form from rendering and from accepting submissions.'
)]
final class DisableFormsCommand extends AbstractKillSwitchCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'form',
                InputArgument::OPTIONAL,
                'Form identifier to block. Omit it to block a whole site (--site) or the entire installation.'
            )
            ->addOption(
                'site',
                's',
                InputOption::VALUE_REQUIRED,
                'Site identifier to block. Ignored when a form identifier is given.'
            )
            ->addOption(
                'for',
                'f',
                InputOption::VALUE_REQUIRED,
                'Lift the block automatically after this long: 90m, 2h, 1d, or a plain number of minutes. '
                    . 'Without it the block stays until it is lifted by hand.'
            )
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'What visitors are told. Without it the localized default text is shown, which is the '
                    . 'only option that is correct in every site language.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $this->resolveScope($input);

        try {
            $until = $this->parseDuration($input->getOption('for'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->killSwitch->disable(
            $target['scope'],
            $target['identifier'],
            'cli',
            trim((string)$input->getOption('message')),
            $until
        );

        $io->success(
            $until === null
                ? 'Blocked ' . $target['label'] . '.'
                : 'Blocked ' . $target['label'] . ' until ' . date('Y-m-d H:i', $until) . '.'
        );
        $io->writeln('The pages that had this form cached were flushed by tag; nothing else was touched.');

        return Command::SUCCESS;
    }
}
