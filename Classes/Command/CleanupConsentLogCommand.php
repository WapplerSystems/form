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
use TYPO3\CMS\Form\Domain\Repository\ConsentLogRepository;

/**
 * Prunes `tx_form_consent_log`, and the wordings nothing refers to any more.
 *
 * Console counterpart to CleanupConsentLogTask, which cannot be created on
 * TYPO3 v13 - see CleanupValidationLogCommand for why the task registration
 * does not take there.
 *
 * The default retention is three years, not the 90 days its siblings use, and
 * the difference is the point: this table is evidence. A consent is challenged
 * long after it was given, so pruning it on a monitoring-log schedule would
 * destroy the record the log exists to keep - an Art. 7(1) GDPR failure dressed
 * up as data hygiene. Three years is the German regelmässige Verjährungsfrist
 * (§ 195 BGB) and a starting point, not advice: the right window follows from
 * the purpose the consent was given for and belongs to whoever runs the site.
 *
 * "Keep forever" is not the safe alternative it looks like - the `subject`
 * column holds an e-mail address, so Art. 5(1)(e) applies here too.
 *
 * Order is deliberate: rows first, then orphaned wordings, because a wording is
 * orphaned only once the last log row referring to it is gone.
 */
#[AsCommand('form:cleanup:consentlog', 'Delete consent-log rows older than the retention period, then orphaned wordings.')]
class CleanupConsentLogCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 1095;

    public function __construct(
        private readonly ConsentLogRepository $consentLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Removes rows from tx_form_consent_log whose crdate is older than the retention' . LF
                . 'period, then removes wordings in tx_form_consent_text that no remaining row' . LF
                . 'refers to.' . LF . LF
                . 'The default is 1095 days (three years), NOT the 90 days used by the mail and' . LF
                . 'validation logs: this table is the evidence that a consent was given, and the' . LF
                . 'wording that was shown. Shortening it discards exactly that. Lengthening it' . LF
                . 'is not free either - the subject column holds an e-mail address.' . LF . LF
                . 'Pick the window from the purpose the consent was given for. Use --dry-run' . LF
                . 'first; on this table that is worth doing every time.'
            )
            ->addOption(
                'retention-period',
                'r',
                InputOption::VALUE_REQUIRED,
                'Minimum age in days before a consent row is deleted.',
                (string)self::DEFAULT_RETENTION_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only report how many rows would be deleted. Orphaned wordings are not counted, '
                . 'because which wordings become orphaned depends on the deletion having happened.',
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
        $affected = $this->consentLogRepository->countOlderThan($cutoff);

        if ((bool)$input->getOption('dry-run')) {
            $io->note(sprintf(
                'Dry-run: %d row(s) older than %d day(s) would be deleted from %s.',
                $affected,
                $retentionDays,
                ConsentLogRepository::TABLE,
            ));
            return Command::SUCCESS;
        }

        $deleted = $this->consentLogRepository->deleteOlderThan($cutoff);
        // Only now can a wording be orphaned.
        $orphanedTexts = $this->consentLogRepository->deleteOrphanedTexts();

        if ($deleted === 0 && $orphanedTexts === 0) {
            $io->success(sprintf(
                'No rows in %s are older than %d day(s), and no orphaned wordings. Nothing to do.',
                ConsentLogRepository::TABLE,
                $retentionDays,
            ));
            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Deleted %d consent row(s) older than %d day(s) from %s, and %d orphaned wording(s) from %s.',
            $deleted,
            $retentionDays,
            ConsentLogRepository::TABLE,
            $orphanedTexts,
            ConsentLogRepository::TEXT_TABLE,
        ));

        return Command::SUCCESS;
    }
}
