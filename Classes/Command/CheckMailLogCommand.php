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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Enum\MailLogStatus;

/**
 * Exits non-zero when a form's notification mail failed, got stuck, or did not
 * happen at all.
 *
 * This is the part that actually closes the loop. A backend module only helps
 * someone who opens it, and the failure that prompted this whole feature ran
 * daily for over ten days on a site whose *mail monitoring form* was the thing
 * that was broken — so nothing raised its hand. A log needs a reader, and on a
 * server the reader is a cron job that can fail loudly.
 *
 * Usage:
 *   # anything wrong in the last 24 h?
 *   bin/typo3 form:maillog:check
 *
 *   # that one form must have sent at least once in the last 25 h
 *   bin/typo3 form:maillog:check --form=monitoring-Mail-Test --min-sent=1 --max-age=1500
 *
 * Exit codes: 0 nothing to report, 1 problems found (or --min-sent not met).
 */
#[AsCommand(
    'form:maillog:check',
    'Exit non-zero when a form notification mail failed, got stuck, or did not happen.'
)]
class CheckMailLogCommand extends Command
{
    private const DEFAULT_MAX_AGE_MINUTES = 1440;

    public function __construct(
        private readonly MailLogRepository $mailLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-age',
                'a',
                InputOption::VALUE_REQUIRED,
                'How far back to look, in minutes.',
                (string)self::DEFAULT_MAX_AGE_MINUTES
            )
            ->addOption(
                'form',
                'f',
                InputOption::VALUE_REQUIRED,
                'Restrict the check to one form identifier.'
            )
            ->addOption(
                'min-sent',
                'm',
                InputOption::VALUE_REQUIRED,
                'Fail when fewer than this many mails were sent successfully in the window. '
                    . 'Needs --form. This is what catches a form that stopped sending entirely — '
                    . 'no rows at all looks identical to "nothing to report" otherwise.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $maxAgeMinutes = max(1, (int)$input->getOption('max-age'));
        $since = time() - $maxAgeMinutes * 60;
        $formIdentifier = $input->getOption('form');
        $formIdentifier = is_string($formIdentifier) && $formIdentifier !== '' ? $formIdentifier : null;
        $minSent = $input->getOption('min-sent');
        $minSent = $minSent === null ? null : max(0, (int)$minSent);

        $problems = $this->mailLogRepository->findProblems($since, $formIdentifier);
        $failed = false;

        foreach ($problems as $row) {
            $failed = true;
            $io->writeln($this->describe($row));
        }

        if ($minSent !== null) {
            if ($formIdentifier === null) {
                $io->error('--min-sent needs --form; without it the number would span every form at once.');
                return Command::FAILURE;
            }
            $sent = $this->mailLogRepository->countSentSince($formIdentifier, $since);
            if ($sent < $minSent) {
                $failed = true;
                $io->writeln(sprintf(
                    '%s: only %d of the expected %d mails were sent in the last %d minutes',
                    $formIdentifier,
                    $sent,
                    $minSent,
                    $maxAgeMinutes
                ));
            }
        }

        if ($failed) {
            $io->error(sprintf('Mail log reports problems in the last %d minutes.', $maxAgeMinutes));
            return Command::FAILURE;
        }

        $io->success(sprintf('No mail problems in the last %d minutes.', $maxAgeMinutes));

        return Command::SUCCESS;
    }

    /**
     * One line per problem, built so a cron mail is readable without opening the
     * backend. A stuck row is called out as "outcome unknown" rather than
     * "failed": nobody can tell whether that mail went out, and saying so is
     * more useful than guessing either way.
     *
     * @param array<string, mixed> $row
     */
    private function describe(array $row): string
    {
        $status = MailLogStatus::tryFrom((int)$row['status']);
        $when = date('Y-m-d H:i:s', (int)$row['crdate']);

        if ($status === MailLogStatus::FAILED) {
            $detail = (string)($row['error_message'] ?? '');
            if ($detail === '') {
                // Withheld because a transport error can quote the recipient.
                $detail = sprintf('error #%d (message not stored)', (int)$row['error_code']);
            }

            return sprintf(
                '%s  FAILED   %s / %s: %s',
                $when,
                $row['form_identifier'],
                $row['finisher_identifier'],
                $detail
            );
        }

        return sprintf(
            '%s  UNKNOWN  %s / %s: started but never reported an outcome (%s)',
            $when,
            $row['form_identifier'],
            $row['finisher_identifier'],
            $status === MailLogStatus::PREPARED
                ? 'died during transport — the mail may or may not have gone out'
                : 'died before the mail was built'
        );
    }
}
