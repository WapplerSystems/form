<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\Finishers;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Model\FormElements\FileUpload;

/**
 * Attaches files uploaded via FileUpload elements to an arbitrary
 * database record by creating new `sys_file_reference` rows that link
 * the underlying `sys_file` records to a given (table, uid, fieldName)
 * tuple.
 *
 * Typical use case: a `SaveToDatabase` finisher creates a new row in a
 * custom table, then `AttachUploadsToObject` connects the uploaded
 * files to that row's FAL field. Use the standard FinisherVariableProvider
 * placeholder syntax to grab the just-inserted UID from
 * `SaveToDatabase`:
 *
 *     finishers:
 *       -
 *         identifier: SaveToDatabase
 *         options:
 *           1:
 *             table: 'tx_myext_record'
 *             ...
 *       -
 *         identifier: AttachUploadsToObject
 *         options:
 *           elements:
 *             myFileUploadField:
 *               table: 'tx_myext_record'
 *               uid: '{SaveToDatabase.insertedUids.1}'
 *               fieldName: 'attachments'
 *
 * Per-element options:
 *   table     - target table name (required)
 *   uid       - target record UID, may use placeholders (required)
 *   fieldName - the FAL field on the target table (required)
 *   pid       - optional explicit pid; defaults to the target record's pid
 *
 * This is a **rebuild** of the legacy `wapplersystems/form_extended`
 * finisher — the original used DataHandler with a fake backend user
 * and `bypassAccessCheckForRecords = true`, hardcoded a single
 * "NEW1234" placeholder (so only ONE file per row would attach), and
 * never updated the FAL counter properly. This rebuild does direct
 * `sys_file_reference` inserts via the ConnectionPool, supports any
 * number of files per element, and updates the inline counter column
 * so the BE edit form shows the attached files correctly.
 *
 * Operators should refresh the TYPO3 reference index (e.g. via the
 * `referenceindex:update` CLI command, or the BE module) if downstream
 * tooling relies on it.
 */
final class AttachUploadsToObjectFinisher extends AbstractFinisher
{
    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'elements' => [],
    ];

    protected function executeInternal(): void
    {
        $elementsConfig = $this->parseOption('elements');
        if (!is_array($elementsConfig) || $elementsConfig === []) {
            return;
        }

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $formRuntime = $this->finisherContext->getFormRuntime();

        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
            if (!$element instanceof FileUpload) {
                continue;
            }
            $identifier = $element->getIdentifier();
            $config = $elementsConfig[$identifier] ?? null;
            if (!is_array($config)) {
                continue;
            }

            $table = (string)($config['table'] ?? '');
            $uid = (int)$this->parseOption('elements.' . $identifier . '.uid');
            $fieldName = (string)($config['fieldName'] ?? '');

            if ($table === '' || $uid === 0 || $fieldName === '') {
                throw new FinisherException(sprintf(
                    'AttachUploadsToObject: element "%s" requires non-empty table, uid and fieldName options.',
                    $identifier,
                ), 1717770000);
            }

            $files = $this->normalizeToFileList($formRuntime[$identifier] ?? null);
            if ($files === []) {
                continue;
            }

            $targetConnection = $connectionPool->getConnectionForTable($table);
            $pid = $config['pid'] ?? null;
            $pid = $pid !== null ? (int)$pid : $this->resolveRecordPid($targetConnection, $table, $uid);

            $referenceConnection = $connectionPool->getConnectionForTable('sys_file_reference');
            $startSorting = $this->resolveNextSorting($referenceConnection, $table, $uid, $fieldName);
            $sorting = $startSorting;
            $now = time();

            foreach ($files as $file) {
                $sysFileUid = (int)$file->getProperty('uid');
                if ($sysFileUid === 0) {
                    continue;
                }
                $referenceConnection->insert('sys_file_reference', [
                    'uid_local' => $sysFileUid,
                    'uid_foreign' => $uid,
                    'tablenames' => $table,
                    'fieldname' => $fieldName,
                    'pid' => $pid,
                    'sorting_foreign' => $sorting,
                    'crdate' => $now,
                    'tstamp' => $now,
                ]);
                $sorting++;
            }

            // Bring the target row's inline-FAL counter up to date so the
            // BE edit form renders the attached files. Counter = highest
            // sorting_foreign just used.
            $targetConnection->update($table, [$fieldName => $sorting - 1], ['uid' => $uid]);
        }
    }

    /**
     * @return list<FileInterface>
     */
    private function normalizeToFileList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        $candidates = match (true) {
            $value instanceof ObjectStorage => iterator_to_array($value),
            is_array($value) => $value,
            default => [$value],
        };
        $files = [];
        foreach ($candidates as $candidate) {
            if ($candidate instanceof ExtbaseFileReference) {
                $candidate = $candidate->getOriginalResource();
            }
            if ($candidate instanceof CoreFileReference) {
                $candidate = $candidate->getOriginalFile();
            }
            if ($candidate instanceof FileInterface) {
                $files[] = $candidate;
            }
        }
        return $files;
    }

    private function resolveRecordPid(Connection $connection, string $table, int $uid): int
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $pid = $queryBuilder
            ->select('pid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER),
                ),
            )
            ->executeQuery()
            ->fetchOne();
        return (int)$pid;
    }

    private function resolveNextSorting(Connection $connection, string $tablenames, int $uidForeign, string $fieldname): int
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $max = $queryBuilder
            ->select('sorting_foreign')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($tablenames)),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($uidForeign, ParameterType::INTEGER),
                ),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($fieldname)),
            )
            ->orderBy('sorting_foreign', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        return ((int)$max) + 1;
    }
}
