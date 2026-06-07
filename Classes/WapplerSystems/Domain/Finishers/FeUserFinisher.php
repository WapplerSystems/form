<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Domain\Finishers;

use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\SaveToDatabaseFinisher;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * Frontend-User registration finisher.
 *
 * Inserts (or updates) a row in `fe_users` from form values. Built on
 * core's SaveToDatabaseFinisher with three frontend-user-specific
 * extensions:
 *
 *  - hardcoded target table `fe_users`
 *  - mandatory `pid` option for the storage page
 *  - per-element `hashPassword: true` runs the value through TYPO3's
 *    PasswordHashFactory ('FE' context) before insert
 *
 * Also keeps the per-element ergonomics inherited from upstream
 * SaveToDatabaseFinisher: skipIfValueIsEmpty, valueIfValueIsEmpty,
 * dateFormat, multi-target mapOnDatabaseColumn (comma-separated).
 *
 * Ported from wapplersystems/form_extended (Phase 3 of the migration).
 */
class FeUserFinisher extends SaveToDatabaseFinisher
{
    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'mode' => 'insert',
        'whereClause' => [],
        'elements' => [],
        'databaseColumnMappings' => [],
        'pid' => null,
        'dataProcessors' => [],
    ];

    /**
     * @throws FinisherException
     */
    protected function executeInternal(): void
    {
        $this->process(0);
    }

    protected function process(int $iterationCount): void
    {
        $this->throwExceptionOnInconsistentConfiguration();

        $table = 'fe_users';
        $elementsConfiguration = $this->parseOption('elements');
        $databaseColumnMappingsConfiguration = $this->parseOption('databaseColumnMappings');

        $this->databaseConnection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $databaseData = [
            'pid' => $this->parseOption('pid'),
            'tstamp' => time(),
        ];

        foreach ((array)$databaseColumnMappingsConfiguration as $columnName => $columnConfiguration) {
            $value = $this->parseOption('databaseColumnMappings.' . $columnName . '.value');
            if (
                empty($value)
                && ($columnConfiguration['skipIfValueIsEmpty'] ?? false) === true
            ) {
                continue;
            }
            if ($this->parseOption('databaseColumnMappings.' . $columnName . '.function') === 'time') {
                $value = time();
            }
            $databaseData[$columnName] = $value;
        }

        $databaseData = $this->prepareData((array)$elementsConfiguration, $databaseData);

        $this->saveToDatabase($databaseData, $table, $iterationCount);
    }

    /**
     * @param array<string, array<string, mixed>> $elementsConfiguration
     * @param array<string, mixed> $databaseData
     * @return array<string, mixed>
     */
    protected function prepareData(array $elementsConfiguration, array $databaseData): array
    {
        foreach ($this->getFormValues() as $elementIdentifier => $elementValue) {
            $elementConfig = $elementsConfiguration[$elementIdentifier] ?? null;
            if (!is_array($elementConfig) || !isset($elementConfig['mapOnDatabaseColumn'])) {
                continue;
            }

            if (($elementValue === null || $elementValue === '')) {
                if (($elementConfig['skipIfValueIsEmpty'] ?? false) === true) {
                    continue;
                }
                if (isset($elementConfig['valueIfValueIsEmpty'])) {
                    $elementValue = $elementConfig['valueIfValueIsEmpty'];
                }
            }

            $element = $this->getElementByIdentifier((string)$elementIdentifier);
            if (!$element instanceof FormElementInterface) {
                continue;
            }

            if ($elementValue instanceof FileReference) {
                $saveAsIdentifier = (bool)($elementConfig['saveFileIdentifierInsteadOfUid'] ?? false);
                $elementValue = $saveAsIdentifier
                    ? $elementValue->getOriginalResource()->getCombinedIdentifier()
                    : $elementValue->getOriginalResource()->getProperty('uid_local');
            } elseif (is_array($elementValue)) {
                $elementValue = implode(',', $elementValue);
            } elseif ($elementValue instanceof \DateTimeInterface) {
                $elementValue = $elementValue->format((string)($elementConfig['dateFormat'] ?? 'U'));
            } elseif (($elementConfig['hashPassword'] ?? false) === true) {
                $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)
                    ->getDefaultHashInstance('FE');
                $elementValue = $hashInstance->getHashedPassword((string)$elementValue);
            }

            foreach (explode(',', (string)$elementConfig['mapOnDatabaseColumn']) as $field) {
                $databaseData[trim($field)] = $elementValue;
            }
        }
        return $databaseData;
    }

    /**
     * @throws FinisherException
     */
    protected function throwExceptionOnInconsistentConfiguration(): void
    {
        parent::throwExceptionOnInconsistentConfiguration();

        if ($this->options['pid'] === null) {
            throw new FinisherException(
                'An empty option "pid" is not allowed for FeUserFinisher.',
                1595979076,
            );
        }
    }
}
