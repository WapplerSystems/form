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

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Prunes `tx_form_submission` and `tx_form_webhook_log` by age.
 *
 * Does the same thing as CleanupSubmissionsTask, but as a console command,
 * because that task cannot be created on TYPO3 v13 — the same registration
 * problem the validation-log, mail-log and consent-log commands already work
 * around: the task type is declared only through
 * ExtensionManagementUtility::addRecordType() on `tx_scheduler_task`, and on
 * v13.4 that table has no TCA, so the override returns at its own guard and
 * TaskService::getAvailableTaskTypes() never learns the class.
 *
 * This table is the one where that gap hurts most. The other three logs hold no
 * submitted values; `tx_form_submission` holds the submission itself —
 * `content` is the JSON of every field the visitor filled in, plus the field
 * labels and an HMAC of their IP. So on v13 the SaveSubmission finisher could
 * be switched on, but the retention window it relies on could not be enforced
 * by any means the extension shipped, which turns a documented "90 days" into
 * "forever" — the Art. 5(1)(e) problem, and on a form that handles
 * cancellations the stored data is a contractual declaration, not a note.
 *
 *   30 3 * * * /path/to/vendor/bin/typo3 form:cleanup:submissions
 *
 * Both tables are pruned in one pass and reported separately, mirroring the
 * task, which treats them as one retention unit: a webhook row is the delivery
 * record of a submission, so outliving it serves nothing.
 */
#[AsCommand('form:cleanup:submissions', 'Delete stored submissions and webhook-log rows older than the retention period.')]
class CleanupSubmissionsCommand extends Command
{
    /**
     * Same list, and same order, as CleanupSubmissionsTask::TABLES.
     *
     * @var list<string>
     */
    private const TABLES = [
        'tx_form_submission',
        'tx_form_webhook_log',
    ];

    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Removes rows from tx_form_submission and tx_form_webhook_log whose crdate is' . LF
                . 'older than the retention period.' . LF . LF
                . 'Unlike the other cleanup commands this one deletes CONTENT: a' . LF
                . 'tx_form_submission row holds every value the visitor submitted. Pick the' . LF
                . 'window from what the stored submissions are actually for - a few weeks is' . LF
                . 'usually plenty to tune a spam filter against real traffic, and there is no' . LF
                . 'reason to keep a copy of a declaration the recipient already has by mail.' . LF . LF
                . 'Equivalent to CleanupSubmissionsTask, which cannot be registered on v13.' . LF . LF
                . 'Use --dry-run to see how many rows would go, and --verbose for the age of' . LF
                . 'the oldest and newest affected row per table.'
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
        $isDryRun = (bool)$input->getOption('dry-run');

        $total = 0;
        foreach (self::TABLES as $table) {
            $total += $this->prune($io, $output, $table, $cutoff, $retentionDays, $isDryRun);
        }

        if ($total === 0) {
            $io->success(sprintf(
                'Nothing older than %d day(s) in %s.',
                $retentionDays,
                implode(' or ', self::TABLES),
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * Number of rows deleted (or, on a dry run, that would be deleted).
     */
    private function prune(
        SymfonyStyle $io,
        OutputInterface $output,
        string $table,
        int $cutoff,
        int $retentionDays,
        bool $isDryRun,
    ): int {
        $connection = $this->connectionPool->getConnectionForTable($table);

        $countQuery = $connection->createQueryBuilder();
        $affected = (int)$countQuery
            ->count('uid')
            ->from($table)
            ->where(
                $countQuery->expr()->lt(
                    'crdate',
                    $countQuery->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        if ($affected === 0) {
            return 0;
        }

        if ($output->isVerbose()) {
            $rangeQuery = $connection->createQueryBuilder();
            $range = $rangeQuery
                ->addSelectLiteral(
                    'MIN(' . $rangeQuery->quoteIdentifier('crdate') . ') AS oldest',
                    'MAX(' . $rangeQuery->quoteIdentifier('crdate') . ') AS newest',
                )
                ->from($table)
                ->where(
                    $rangeQuery->expr()->lt(
                        'crdate',
                        $rangeQuery->createNamedParameter($cutoff, ParameterType::INTEGER),
                    ),
                )
                ->executeQuery()
                ->fetchAssociative() ?: [];
            if ($range !== []) {
                $io->writeln(sprintf(
                    '  %s affected range: %s .. %s',
                    $table,
                    date('Y-m-d H:i:s', (int)$range['oldest']),
                    date('Y-m-d H:i:s', (int)$range['newest']),
                ));
            }
        }

        if ($isDryRun) {
            $io->note(sprintf(
                'Dry-run: %d row(s) older than %d day(s) would be deleted from %s.',
                $affected,
                $retentionDays,
                $table,
            ));
            return $affected;
        }

        $deleteQuery = $connection->createQueryBuilder();
        $deleted = (int)$deleteQuery
            ->delete($table)
            ->where(
                $deleteQuery->expr()->lt(
                    'crdate',
                    $deleteQuery->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeStatement();

        $io->success(sprintf(
            'Deleted %d row(s) older than %d day(s) from %s.',
            $deleted,
            $retentionDays,
            $table,
        ));

        return $deleted;
    }
}
