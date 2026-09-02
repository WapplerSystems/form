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
 * Prunes `tx_form_validation_log` by age.
 *
 * Does the same thing as CleanupValidationLogTask, but as a console command,
 * because that task cannot be created on TYPO3 v13: it is registered solely
 * through ExtensionManagementUtility::addRecordType() on `tx_scheduler_task`
 * with `tasktype` in its showitem, which is the newer scheduler architecture.
 * On v13.4 that table has no TCA at all (see the comment in
 * cms-scheduler/ext_tables.sql) and no `tasktype` column, so the TCA override
 * returns early at its own guard and TaskService::getAvailableTaskTypes() -
 * which reads $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']
 * on v13 - never learns about the class. The task type is then absent from the
 * scheduler module, and SchedulerTaskRepository::getGroupedTasks() silently
 * drops rows of unknown type, so even a hand-written row is ignored.
 *
 * A console command sidesteps all of that and behaves identically on v13 and
 * v14, so it can be driven straight from cron:
 *
 *   30 3 * * * /path/to/vendor/bin/typo3 form:cleanup:validationlog
 *
 * The log is opt-in per form (renderingOptions.recordValidationFailures), and
 * a form under active spam pressure produces roughly one row per validator per
 * submission, so an unpruned table grows fast.
 *
 * Note: tx_form_mail_log and tx_form_consent_log have cleanup tasks with the
 * same registration problem. They are not covered here.
 */
#[AsCommand('form:cleanup:validationlog', 'Delete validation-log rows older than the retention period.')]
class CleanupValidationLogCommand extends Command
{
    private const TABLE_NAME = 'tx_form_validation_log';
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
                'Removes rows from ' . self::TABLE_NAME . ' whose crdate is older than the' . LF
                . 'retention period. Rows hold no submitted values - only form and element' . LF
                . 'identifiers, error codes, the already-translated message and an HMAC of the' . LF
                . 'form session - so pruning only costs statistics, never content.' . LF . LF
                . 'Equivalent to CleanupValidationLogTask, which cannot be registered on v13.' . LF . LF
                . 'Use --dry-run to see how many rows would go, and --verbose for the age of' . LF
                . 'the oldest and newest affected row.'
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

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);

        $countQuery = $connection->createQueryBuilder();
        $affected = (int)$countQuery
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $countQuery->expr()->lt(
                    'crdate',
                    $countQuery->createNamedParameter($cutoff, ParameterType::INTEGER),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        if ($affected === 0) {
            $io->success(sprintf(
                'No rows in %s are older than %d day(s). Nothing to do.',
                self::TABLE_NAME,
                $retentionDays,
            ));
            return Command::SUCCESS;
        }

        if ($output->isVerbose()) {
            $rangeQuery = $connection->createQueryBuilder();
            $range = $rangeQuery
                ->addSelectLiteral(
                    'MIN(' . $rangeQuery->quoteIdentifier('crdate') . ') AS oldest',
                    'MAX(' . $rangeQuery->quoteIdentifier('crdate') . ') AS newest',
                )
                ->from(self::TABLE_NAME)
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
                    '  affected range: %s .. %s',
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
                self::TABLE_NAME,
            ));
            return Command::SUCCESS;
        }

        $deleteQuery = $connection->createQueryBuilder();
        $deleted = (int)$deleteQuery
            ->delete(self::TABLE_NAME)
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
            self::TABLE_NAME,
        ));

        return Command::SUCCESS;
    }
}
