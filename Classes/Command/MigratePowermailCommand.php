<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Form\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Form\Service\PowermailMigrationService;

/**
 * CLI command to migrate Powermail forms to ext:form YAML definitions.
 *
 * Usage examples:
 *   # Migrate all Powermail forms (dry-run first to review)
 *   bin/typo3 form:powermail:migrate --dry-run
 *
 *   # Migrate all forms for real
 *   bin/typo3 form:powermail:migrate
 *
 *   # Migrate a single form by UID
 *   bin/typo3 form:powermail:migrate --form=42
 *
 *   # Migrate translated forms
 *   bin/typo3 form:powermail:migrate --language=1
 *
 *   # Write to a custom storage folder
 *   bin/typo3 form:powermail:migrate --storage=1:/user_upload/forms/
 */
#[AsCommand('form:powermail:migrate', 'Migrate Powermail forms (tx_powermail_domain_model_form) to ext:form YAML definitions')]
final class MigratePowermailCommand extends Command
{
    public function __construct(
        private readonly PowermailMigrationService $migrationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Reads Powermail forms, pages, and fields from the database and creates' . LF
                . 'ext:form YAML form definitions in the configured storage.' . LF . LF
                . 'The command maps Powermail field types to ext:form renderable types,' . LF
                . 'derives EmailToReceiver / EmailToSender finishers from the Powermail' . LF
                . 'plugin flexform, and persists the result via FormPersistenceManager.' . LF . LF
                . 'Use --dry-run first to preview what will be created or updated.' . LF
                . 'Use --form=<uid> to migrate only a single form.' . LF
                . 'Use --language=<sys_language_uid> to migrate translations (default: 0).'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview which forms would be migrated without writing anything.',
            )
            ->addOption(
                'form',
                null,
                InputOption::VALUE_REQUIRED,
                'Migrate only the Powermail form with this UID. If omitted, all forms are migrated.',
            )
            ->addOption(
                'language',
                null,
                InputOption::VALUE_REQUIRED,
                'sys_language_uid to migrate (default: 0 for default language).',
                '0',
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NEGATABLE,
                'Overwrite an existing form definition with the same identifier (default: true).',
                true,
            )
            ->addOption(
                'storage',
                null,
                InputOption::VALUE_REQUIRED,
                'Combined folder identifier for the target storage, e.g. "1:/form_definitions/". '
                . 'If omitted, the storage is auto-detected from the ext:form persistence '
                . 'configuration (allowedFileMounts) with a fallback to the default file storage.',
                '',
            )
            ->addOption(
                'convert-plugins',
                null,
                InputOption::VALUE_NONE,
                'Also switch the tt_content Powermail plugins (CType "powermail_pi1") that embed a '
                . 'migrated form over to the ext:form plugin (CType "form_formframework") pointing to '
                . 'the generated form definition. Run the migration once with this flag: the email '
                . 'finisher configuration is read from the Powermail plugin flexform before it is replaced.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();

        $request = (new ServerRequest('https://localhost/', 'GET'));
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
                           ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool)$input->getOption('dry-run');
        $overwrite = (bool)$input->getOption('overwrite');
        $language = (int)$input->getOption('language');
        $storage = (string)$input->getOption('storage');
        $onlyFormUid = $input->getOption('form') !== null ? (int)$input->getOption('form') : null;
        $convertPlugins = (bool)$input->getOption('convert-plugins');

        if ($dryRun) {
            $io->note('Dry-run mode: no form definitions will be written.');
        }
        if ($convertPlugins) {
            $io->note('Plugin conversion enabled: powermail_pi1 plugins will be switched to form_formframework.');
        }

        $results = $this->migrationService->migrateAll($language, $dryRun, $overwrite, $storage, $onlyFormUid, $convertPlugins);

        if ($results === []) {
            $io->warning('No Powermail forms found.');
            return Command::SUCCESS;
        }

        $rows = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0, 'other' => 0];

        foreach ($results as $row) {
            $status = $row['status'];
            $displayStatus = match ($status) {
                'created', 'would create' => '<info>created</info>',
                'updated', 'would update' => '<info>updated</info>',
                'skipped' => '<comment>skipped</comment>',
                'error' => '<error>error</error>',
                default => '<comment>' . $status . '</comment>',
            };

            $message = $row['message'] !== '' ? ' (' . $row['message'] . ')' : '';

            $rows[] = [
                $row['uid'],
                $row['title'],
                $row['identifier'],
                $row['pages'],
                $row['fields'],
                $displayStatus . $message,
            ];

            if (str_starts_with($status, 'would create') || $status === 'created') {
                $counts['created']++;
            } elseif (str_starts_with($status, 'would update') || $status === 'updated') {
                $counts['updated']++;
            } elseif ($status === 'skipped') {
                $counts['skipped']++;
            } elseif ($status === 'error') {
                $counts['error']++;
            } else {
                $counts['other']++;
            }
        }

        $io->table(['Form UID', 'Title', 'Identifier', '#Pages', '#Fields', 'Status'], $rows);

        $summary = sprintf(
            'Total: %d form(s) processed. Created: %d, Updated: %d, Skipped: %d, Errors: %d',
            count($results),
            $counts['created'],
            $counts['updated'],
            $counts['skipped'],
            $counts['error'],
        );

        if ($dryRun) {
            $io->note($summary);
        } elseif ($counts['error'] === 0) {
            $io->success($summary);
        } else {
            $io->warning($summary);
        }

        if ($dryRun && $counts['created'] + $counts['updated'] > 0) {
            $io->note('Re-run without --dry-run to execute the migration.');
        }

        return $counts['error'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
