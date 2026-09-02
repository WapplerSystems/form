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

/**
 * Prunes `tx_form_mail_log` by age.
 *
 * Console counterpart to CleanupMailLogTask, which cannot be created on
 * TYPO3 v13 - see CleanupValidationLogCommand for why the task registration
 * does not take there.
 *
 * Retention matters more here than for the validation log: a row can carry a
 * recipient address when its form opted in, so this is storage limitation
 * (Art. 5(1)(e) GDPR), not housekeeping. For a log holding addresses, consider
 * a window shorter than the 90-day default.
 *
 *   30 3 * * * /path/to/vendor/bin/typo3 form:cleanup:maillog
 *
 * Deletion is by age only - it does not spare rows still in a non-terminal
 * status, matching the task. A row abandoned months ago has told its story,
 * and the module derives "aborted" from age rather than from a written flag.
 */
#[AsCommand('form:cleanup:maillog', 'Delete mail-log rows older than the retention period.')]
class CleanupMailLogCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly MailLogRepository $mailLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Removes rows from tx_form_mail_log whose crdate is older than the retention' . LF
                . 'period. Equivalent to CleanupMailLogTask, which cannot be registered on v13.' . LF . LF
                . 'Unlike the validation log, a row here may contain a recipient address, a' . LF
                . 'subject and a sender - but only for forms that opted in. Never stored: the' . LF
                . 'message body, submitted values, CC/BCC, attachment names, IP, user agent.' . LF . LF
                . 'Use --dry-run to see how many rows would go.'
            )
            ->addOption(
                'retention-period',
                'r',
                InputOption::VALUE_REQUIRED,
                'Minimum age in days before a row is deleted.',
                (string)self::DEFAULT_RETENTION_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only report how many rows would be deleted.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retentionDays = (int)$input->getOption('retention-period');
        if ($retentionDays < 1) {
            $io->error('The retention period must be at least 1 day.');
            return Command::FAILURE;
        }

        $cutoff = time() - $retentionDays * 86400;
        $affected = $this->mailLogRepository->countOlderThan($cutoff);

        if ($affected === 0) {
            $io->success(sprintf(
                'No rows in %s are older than %d day(s). Nothing to do.',
                MailLogRepository::TABLE_NAME,
                $retentionDays,
            ));
            return Command::SUCCESS;
        }

        if ((bool)$input->getOption('dry-run')) {
            $io->note(sprintf(
                'Dry-run: %d row(s) older than %d day(s) would be deleted from %s.',
                $affected,
                $retentionDays,
                MailLogRepository::TABLE_NAME,
            ));
            return Command::SUCCESS;
        }

        $deleted = $this->mailLogRepository->deleteOlderThan($cutoff);

        $io->success(sprintf(
            'Deleted %d row(s) older than %d day(s) from %s.',
            $deleted,
            $retentionDays,
            MailLogRepository::TABLE_NAME,
        ));

        return Command::SUCCESS;
    }
}
