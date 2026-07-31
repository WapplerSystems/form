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

namespace TYPO3\CMS\Form\Service;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Configuration\PersistenceConfigurationService;
use TYPO3\CMS\Form\Slot\FilePersistenceSlot;

/**
 * Migrates Powermail forms (tx_powermail_domain_model_form) into ext:form
 * YAML form definitions.
 *
 * Reads form, page, and field records via ConnectionPool/QueryBuilder,
 * maps Powermail field types to ext:form renderable types, derives
 * EmailToReceiver / EmailToSender finishers from the Powermail plugin
 * flexform (tt_content.CType='powermail_pi1'), and persists the result
 * through FormPersistenceManagerInterface.
 *
 * @internal
 */
final class PowermailMigrationService
{
    private const TYPE_MAP = [
        'input' => 'Text',
        'textarea' => 'Textarea',
        'select' => 'SingleSelect',
        'check' => 'MultiCheckbox',
        'radio' => 'RadioButton',
        'hidden' => 'Hidden',
        'text' => 'StaticText',
        'html' => 'StaticText',
        'date' => 'Date',
        'file' => 'FileUpload',
        'country' => 'SingleSelect',
        'password' => 'Password',
    ];

    private const SKIP_TYPES = ['submit', 'reset', 'captcha', 'typoscript', 'location'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $pluginSettingsMap = null;

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly FilePersistenceSlot $filePersistenceSlot,
        private readonly PersistenceConfigurationService $persistenceConfigurationService,
    ) {}

    /**
     * Resolve the target file storage + folder for the migrated form definitions.
     *
     * When $storage is empty the storage is auto-detected: the first mount configured
     * in the ext:form "persistenceManager.allowedFileMounts" is preferred (so the forms
     * land where ext:form reads them); the folder path defaults to "/form_definitions/".
     * The storage UID from the config/option is only used when that storage actually
     * exists - otherwise (e.g. the ext:form default "1:/" on installations whose
     * fileadmin storage has a different UID) it falls back to the default file storage.
     *
     * @return array{0: ResourceStorage, 1: Folder}
     */
    private function resolveStorageAndFolder(string $storage): array
    {
        $storageUid = 0;
        $folderPath = '/form_definitions/';

        if ($storage !== '' && str_contains($storage, ':')) {
            // Explicit --storage option, e.g. "2:/form_definitions/".
            [$uidPart, $pathPart] = explode(':', $storage, 2);
            $storageUid = (int)$uidPart;
            $folderPath = '/' . trim($pathPart, '/') . '/';
        } else {
            // Auto-detect from the ext:form persistence configuration.
            $allowedFileMounts = $this->persistenceConfigurationService->getAllowedFileMounts();
            $firstMount = is_array($allowedFileMounts) ? (string)(reset($allowedFileMounts) ?: '') : '';
            if ($firstMount !== '' && str_contains($firstMount, ':')) {
                [$uidPart, $pathPart] = explode(':', $firstMount, 2);
                $storageUid = (int)$uidPart;
                $folderPath = '/' . trim($pathPart, '/') . '/';
            }
        }

        $storageObject = $storageUid > 0 ? $this->storageRepository->findByUid($storageUid) : null;
        // Fall back to the default file storage (its UID is not necessarily 1 - it is 2
        // on this installation) so an unresolvable/omitted UID still targets fileadmin.
        $storageObject ??= $this->storageRepository->getDefaultStorage();
        if ($storageObject === null) {
            throw new \RuntimeException('No writable file storage found (requested UID ' . $storageUid . ', no default storage available).');
        }

        return [$storageObject, $this->ensureFolder($storageObject, $folderPath)];
    }

    /**
     * Migrate all (or a single) Powermail forms into ext:form definitions.
     *
     * @param int $language sys_language_uid to migrate (0 = default language)
     * @param bool $dryRun Do everything except persist; report what would happen
     * @param bool $overwrite Replace an existing form definition with the same identifier
     * @param string $storage Combined folder identifier, e.g. '1:/form_definitions/'
     * @param int|null $onlyFormUid If set, migrate only this single powermail form UID
     * @return list<array{uid: int, title: string, identifier: string, pages: int, fields: int, status: string, message: string}>
     */
    public function migrateAll(int $language, bool $dryRun, bool $overwrite, string $storage, ?int $onlyFormUid = null, bool $convertPlugins = false): array
    {
        $this->pluginSettingsMap = null;
        $results = [];
        $forms = $this->queryForms($language, $onlyFormUid);

        foreach ($forms as $form) {
            try {
                $results[] = $this->migrateSingleForm($form, $language, $dryRun, $overwrite, $storage, $convertPlugins);
            } catch (\Throwable $e) {
                $results[] = [
                    'uid' => (int)$form['uid'],
                    'title' => $form['title'] ?? '',
                    'identifier' => '',
                    'pages' => 0,
                    'fields' => 0,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $form
     * @return array{uid: int, title: string, identifier: string, pages: int, fields: int, status: string, message: string}
     */
    private function migrateSingleForm(array $form, int $language, bool $dryRun, bool $overwrite, string $storage, bool $convertPlugins = false): array
    {
        $formUid = (int)$form['uid'];
        $pages = $this->queryPages($formUid, $language);
        $identifier = $this->buildIdentifier($formUid, $form['title']);

        [$storageObject, $folder] = $this->resolveStorageAndFolder($storage);

        $fileName = $identifier . '.form.yaml';
        $existed = $folder->hasFile($fileName);

        if ($existed && !$overwrite) {
            return [
                'uid' => $formUid,
                'title' => $form['title'],
                'identifier' => $identifier,
                'pages' => count($pages),
                'fields' => 0,
                'status' => 'skipped',
                'message' => 'Form already exists (use --overwrite to replace)',
            ];
        }

        // Preserve finishers from an existing definition (e.g. after --convert-plugins the
        // Powermail plugin's e-mail config is no longer available to re-derive).
        $existingFinishers = [];
        if ($existed) {
            try {
                $existingArray = Yaml::parse($folder->getFile($fileName)->getContents());
                $existingFinishers = is_array($existingArray['finishers'] ?? null) ? $existingArray['finishers'] : [];
            } catch (\Throwable) {
                $existingFinishers = [];
            }
        }

        $fieldCount = 0;
        $renderables = $this->buildRenderables($pages, $language, $fieldCount);
        $formArray = $this->buildFormArray($identifier, $form, $renderables, $formUid, $existingFinishers);

        $yaml = Yaml::dump($formArray, 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $combinedFileIdentifier = $folder->getCombinedIdentifier() . $fileName;

        if (!$dryRun) {
            // The FilePersistenceSlot security guard denies every FAL write to a
            // *.form.yaml file unless the operation is announced beforehand on the
            // shared slot instance (the one that receives the FAL events).
            $signature = $this->filePersistenceSlot->getContentSignature($yaml);
            if ($existed) {
                $this->filePersistenceSlot->allowInvocation(FilePersistenceSlot::COMMAND_FILE_SET_CONTENTS, $combinedFileIdentifier, $signature);
                $file = $folder->getFile($fileName);
                $storageObject->setFileContents($file, $yaml);
            } else {
                $this->filePersistenceSlot->allowInvocation(FilePersistenceSlot::COMMAND_FILE_CREATE, $combinedFileIdentifier);
                $this->filePersistenceSlot->allowInvocation(FilePersistenceSlot::COMMAND_FILE_SET_CONTENTS, $combinedFileIdentifier, $signature);
                $file = $folder->createFile($fileName);
                $storageObject->setFileContents($file, $yaml);
            }
        }

        $actionVerb = $dryRun ? 'would ' . ($existed ? 'update' : 'create') : ($existed ? 'updated' : 'created');

        $message = '';
        if ($convertPlugins) {
            $converted = $this->convertPluginsForForm($formUid, $combinedFileIdentifier, $dryRun);
            $message = $converted > 0
                ? ($dryRun ? 'would convert ' : 'converted ') . $converted . ' plugin(s)'
                : 'no powermail_pi1 plugins found';
        }

        return [
            'uid' => $formUid,
            'title' => $form['title'],
            'identifier' => $identifier,
            'pages' => count($pages),
            'fields' => $fieldCount,
            'status' => $actionVerb,
            'message' => $message,
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array{identifier: string, type: 'Page', label: string, renderables: list<array<string, mixed>>}>
     */
    private function buildRenderables(array $pages, int $language, int &$fieldCount): array
    {
        $renderables = [];

        foreach ($pages as $pageIndex => $page) {
            $pageIdentifier = 'page-' . ($pageIndex + 1);
            $pageRenderables = [];
            $fields = $this->queryFields((int)$page['uid'], $language);

            foreach ($fields as $field) {
                $element = $this->mapField($field, $fieldCount);
                if ($element !== null) {
                    $pageRenderables[] = $element;
                }
            }

            if ($pageRenderables !== []) {
                $renderables[] = [
                    'identifier' => $pageIdentifier,
                    'type' => 'Page',
                    'label' => $page['title'] ?: 'Page ' . ($pageIndex + 1),
                    'renderables' => $pageRenderables,
                ];
            }
        }

        if ($renderables === []) {
            $renderables[] = [
                'identifier' => 'page-1',
                'type' => 'Page',
                'label' => 'Page',
                'renderables' => [],
            ];
        }

        return $renderables;
    }

    /**
     * @param list<array{identifier: string, type: 'Page', label: string, renderables: list<array<string, mixed>>}> $renderables
     * @return array{identifier: string, type: 'Form', prototypeName: string, label: string, renderables: list<array<string, mixed>>, finishers: list<array<string, mixed>>}
     */
    private function buildFormArray(string $identifier, array $form, array $renderables, int $formUid, array $existingFinishers = []): array
    {
        return [
            'identifier' => $identifier,
            'type' => 'Form',
            'prototypeName' => 'standard',
            'label' => $form['title'] ?? $identifier,
            'renderables' => $renderables,
            'finishers' => $this->buildFinishers($formUid, $existingFinishers),
        ];
    }

    /**
     * Map a single Powermail field record into an ext:form renderable array.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>|null null if the field type should be skipped
     */
    private function mapField(array $field, int &$fieldCount): ?array
    {
        $type = $field['type'] ?? '';

        if (in_array($type, self::SKIP_TYPES, true)) {
            return null;
        }

        $marker = ($field['marker'] ?? '') !== '' ? $field['marker'] : ('field-' . $field['uid']);
        $fieldIdentifier = $this->sanitizeIdentifier($marker);

        // Powermail "content" fields embed a tt_content element (e.g. a consent/privacy
        // text). Map them to the ext:form ContentElement so the embedded content carries
        // over. Skip only when no content element is referenced.
        if ($type === 'content') {
            $contentElementUid = (int)($field['content_element'] ?? 0);
            if ($contentElementUid <= 0) {
                return null;
            }
            $fieldCount++;
            return [
                'identifier' => $fieldIdentifier,
                'type' => 'ContentElement',
                'label' => (string)($field['title'] ?? ''),
                'properties' => [
                    'contentElementUid' => $contentElementUid,
                ],
            ];
        }

        $elementType = self::TYPE_MAP[$type] ?? 'Text';

        if ($type === 'select' && ($field['multiselect'] ?? 0) == 1) {
            $elementType = 'MultiSelect';
        } elseif ($type === 'select') {
            $elementType = 'SingleSelect';
        }

        $element = [
            'identifier' => $fieldIdentifier,
            'type' => $elementType,
            'label' => $field['title'] ?? $fieldIdentifier,
        ];

        $prefillValue = $field['prefill_value'] ?? '';
        if ($prefillValue !== '' && $type === 'hidden') {
            $element['defaultValue'] = $prefillValue;
        } elseif ($prefillValue !== '') {
            $element['defaultValue'] = $prefillValue;
        }

        $properties = [];
        $fluidAdditionalAttributes = [];

        $placeholder = $field['placeholder'] ?? '';
        if ($placeholder !== '' && !in_array($type, ['text', 'html', 'hidden'], true)) {
            $fluidAdditionalAttributes['placeholder'] = $placeholder;
        }

        if (($field['mandatory'] ?? 0) == 1) {
            $fluidAdditionalAttributes['required'] = 'required';
        }

        if ($fluidAdditionalAttributes !== []) {
            $properties['fluidAdditionalAttributes'] = $fluidAdditionalAttributes;
        }

        if (in_array($type, ['select', 'check', 'radio'], true) && !empty($field['settings'])) {
            $parsedOptions = $this->parseFieldOptions($field['settings']);
            $elementOptions = [];
            foreach ($parsedOptions as $value => $label) {
                $elementOptions[$value] = $label;
            }
            if ($elementOptions !== []) {
                $properties['options'] = $elementOptions;
            }
        }

        if ($type === 'text' || $type === 'html') {
            $properties['text'] = $field['text'] ?? '';
        }

        if ($type === 'file') {
            $properties['allowedMimeTypes'] = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/gif',
            ];
        }

        if ($properties !== []) {
            $element['properties'] = $properties;
        }

        $validators = [];

        if (($field['mandatory'] ?? 0) == 1) {
            $validators[] = ['identifier' => 'NotEmpty'];
        }

        $elementValidators = $this->buildValidators($field);
        foreach ($elementValidators as $validator) {
            $validators[] = $validator;
        }

        if ($validators !== []) {
            $element['validators'] = $validators;
        }

        $fieldCount++;
        return $element;
    }

    /**
     * @param array<string, mixed> $field
     * @return list<array<string, mixed>>
     */
    private function buildValidators(array $field): array
    {
        $type = $field['type'] ?? '';
        $validation = (int)($field['validation'] ?? 0);
        $validationConfig = $field['validation_configuration'] ?? '';
        $marker = $field['marker'] ?? '';
        $validators = [];

        if ($validation <= 0 && !preg_match('/e-?mail/i', $marker)) {
            return $validators;
        }

        switch ($validation) {
            case 0:
                break;
            case 1:
                $validators[] = ['identifier' => 'EmailAddress'];
                break;
            case 2:
                $validators[] = [
                    'identifier' => 'RegularExpression',
                    'options' => ['regularExpression' => '/^https?:\/\/.+/i'],
                ];
                break;
            case 4:
                $validators[] = ['identifier' => 'Number'];
                break;
            case 5:
                $validators[] = [
                    'identifier' => 'RegularExpression',
                    'options' => ['regularExpression' => '/^[a-zA-Z]+$/'],
                ];
                break;
            case 6:
                if ($validationConfig !== '') {
                    $validators[] = [
                        'identifier' => 'Number',
                        'options' => ['minimum' => (int)$validationConfig],
                    ];
                }
                break;
            case 7:
                if ($validationConfig !== '') {
                    $validators[] = [
                        'identifier' => 'Number',
                        'options' => ['maximum' => (int)$validationConfig],
                    ];
                }
                break;
            case 8:
                if ($validationConfig !== '') {
                    $parts = explode(',', $validationConfig);
                    if (count($parts) >= 2) {
                        $validators[] = [
                            'identifier' => 'Number',
                            'options' => [
                                'minimum' => (int)trim($parts[0]),
                                'maximum' => (int)trim($parts[1]),
                            ],
                        ];
                    }
                }
                break;
            case 9:
                if ($validationConfig !== '') {
                    $validators[] = [
                        'identifier' => 'StringLength',
                        'options' => ['minimum' => (int)$validationConfig],
                    ];
                }
                break;
            case 10:
                if ($validationConfig !== '') {
                    $validators[] = [
                        'identifier' => 'RegularExpression',
                        'options' => ['regularExpression' => $validationConfig],
                    ];
                }
                break;
        }

        if ($validation !== 1 && preg_match('/e-?mail/i', $marker)) {
            $validators[] = ['identifier' => 'EmailAddress'];
        }

        return $validators;
    }

    /**
     * Parse Powermail "settings" field: newline-separated options.
     * Each line is "value|label" or just "label" (value = label).
     *
     * @return array<string, string> value => label
     */
    private function parseFieldOptions(string $settings): array
    {
        $options = [];
        $lines = preg_split('/\r?\n/', trim($settings));
        if (!is_array($lines)) {
            return $options;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $value = trim($parts[0]);
                $label = trim($parts[1]);
            } else {
                $value = $label = trim($line);
            }
            if ($value !== '') {
                $options[$value] = $label;
            }
        }
        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFinishers(int $formUid, array $existingFinishers = []): array
    {
        $pluginSettings = $this->getPluginSettings($formUid);

        // Re-run safety: once the Powermail plugin has been switched to form_formframework
        // (--convert-plugins) its receiver/sender e-mail config is gone. Keep the finishers
        // already present in the existing form definition instead of writing empty placeholders.
        if ($pluginSettings === null && $existingFinishers !== []) {
            return $existingFinishers;
        }

        $finishers = [];

        $receiverFlex = $pluginSettings['settings']['flexform']['receiver'] ?? [];
        $senderFlex = $pluginSettings['settings']['flexform']['sender'] ?? [];

        $receiverEmail = (string)($receiverFlex['email'] ?? '');
        $receiverSubject = (string)($receiverFlex['subject'] ?? '');
        $senderEmail = (string)($senderFlex['email'] ?? '');
        $senderName = (string)($senderFlex['name'] ?? '');
        $senderSubject = (string)($senderFlex['subject'] ?? '');

        if ($receiverEmail !== '') {
            $finishers[] = [
                'identifier' => 'EmailToReceiver',
                'options' => [
                    'subject' => $receiverSubject !== '' ? $receiverSubject : 'Form submission',
                    'recipients' => [
                        $receiverEmail => $receiverEmail,
                    ],
                    'senderAddress' => $senderEmail !== '' ? $senderEmail : $receiverEmail,
                    'senderName' => $senderName !== '' ? $senderName : $receiverEmail,
                    'format' => 'html',
                    'attachUploads' => true,
                    'useFluidEmail' => true,
                ],
            ];
        } else {
            $finishers[] = [
                'identifier' => 'EmailToReceiver',
                'options' => [
                    'subject' => 'Form submission',
                    'recipients' => [],
                    'senderAddress' => '',
                    'senderName' => '',
                    'format' => 'html',
                    'attachUploads' => true,
                    'useFluidEmail' => true,
                ],
            ];
        }

        if ($senderEmail !== '') {
            $finishers[] = [
                'identifier' => 'EmailToSender',
                'options' => [
                    'subject' => $senderSubject !== '' ? $senderSubject : 'Thank you for your submission',
                    'senderAddress' => $receiverEmail !== '' ? $receiverEmail : $senderEmail,
                    'senderName' => $receiverEmail !== '' ? $receiverEmail : $senderEmail,
                    'format' => 'html',
                    'useFluidEmail' => true,
                ],
            ];
        }

        $finishers[] = [
            'identifier' => 'Confirmation',
            'options' => [
                'message' => 'Form was submitted successfully.',
            ],
        ];

        return $finishers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPluginSettings(int $formUid): ?array
    {
        if ($this->pluginSettingsMap === null) {
            $this->pluginSettingsMap = $this->buildPluginSettingsMap();
        }
        return $this->pluginSettingsMap[$formUid] ?? null;
    }

    /**
     * Query all powermail_pi1 plugins and index them by selected form UID.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPluginSettingsMap(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->select('uid', 'pi_flexform')
            ->from('tt_content')
            ->where($qb->expr()->eq('CType', $qb->createNamedParameter('powermail_pi1')))
            ->andWhere($qb->expr()->neq('pi_flexform', $qb->createNamedParameter('')))
            ->andWhere($qb->expr()->isNotNull('pi_flexform'));

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $map = [];

        foreach ($rows as $row) {
            if (empty($row['pi_flexform'])) {
                continue;
            }
            $flexform = $flexFormTools->convertFlexFormContentToArray($row['pi_flexform']);
            $selectedForm = (int)($flexform['settings']['flexform']['main']['form'] ?? 0);
            if ($selectedForm > 0 && !isset($map[$selectedForm])) {
                $map[$selectedForm] = $flexform;
            }
        }

        return $map;
    }

    /**
     * Ensure a folder path exists inside the given storage, creating any missing
     * parent folders along the way.
     */
    /**
     * Switch the tt_content Powermail plugins (CType "powermail_pi1") that embed the given
     * Powermail form over to the ext:form plugin (CType "form_formframework"), pointing to the
     * migrated form definition. Idempotent: already-converted plugins are no longer
     * "powermail_pi1" and are left untouched on re-runs.
     */
    private function convertPluginsForForm(int $powermailFormUid, string $persistenceIdentifier, bool $dryRun): int
    {
        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('powermail_pi1')),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        $count = 0;
        foreach ($rows as $row) {
            $flexform = (string)($row['pi_flexform'] ?? '');
            $settings = $flexform !== '' ? $flexFormTools->convertFlexFormContentToArray($flexform) : [];
            if ((int)($settings['settings']['flexform']['main']['form'] ?? 0) !== $powermailFormUid) {
                continue;
            }
            if (!$dryRun) {
                $connection->update(
                    'tt_content',
                    [
                        'CType' => 'form_formframework',
                        'list_type' => '',
                        'pi_flexform' => $this->buildFormPluginFlexform($persistenceIdentifier),
                    ],
                    ['uid' => (int)$row['uid']],
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * Build the tt_content pi_flexform for an ext:form "form_formframework" plugin that
     * references the given form definition (combined file identifier).
     */
    private function buildFormPluginFlexform(string $persistenceIdentifier): string
    {
        $value = htmlspecialchars($persistenceIdentifier, ENT_QUOTES | ENT_XML1);

        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . "\n"
            . '<T3FlexForms>' . "\n"
            . '    <data>' . "\n"
            . '        <sheet index="sDEF">' . "\n"
            . '            <language index="lDEF">' . "\n"
            . '                <field index="settings.persistenceIdentifier">' . "\n"
            . '                    <value index="vDEF">' . $value . '</value>' . "\n"
            . '                </field>' . "\n"
            . '            </language>' . "\n"
            . '        </sheet>' . "\n"
            . '    </data>' . "\n"
            . '</T3FlexForms>' . "\n";
    }

    private function ensureFolder(ResourceStorage $storage, string $folderPath): Folder
    {
        $folder = $storage->getRootLevelFolder();
        $segments = explode('/', trim($folderPath, '/'));
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($folder->hasFolder($segment)) {
                $folder = $folder->getSubfolder($segment);
            } else {
                $folder = $folder->createFolder($segment);
            }
        }
        return $folder;
    }

    /**
     * Build a stable, unique form identifier from the Powermail form UID and title.
     *
     * Result matches ^[a-z][a-z0-9-]*$.
     */
    private function buildIdentifier(int $uid, string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = $slug ?: 'form';
        return 'powermail-' . $uid . '-' . $slug;
    }

    /**
     * Sanitize a field marker or identifier to ^[a-z][a-z0-9-]*$.
     */
    private function sanitizeIdentifier(string $input): string
    {
        $input = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $input), '-'));
        $input = preg_replace('/[^a-z0-9-]/', '', $input);
        if ($input === '' || !preg_match('/^[a-z]/', $input)) {
            $input = 'field-' . ($input ?: 'unnamed');
        }
        return $input;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queryForms(int $language, ?int $onlyFormUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_powermail_domain_model_form');
        $qb->select('*')
            ->from('tx_powermail_domain_model_form')
            ->where($qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($language, ParameterType::INTEGER)))
            ->orderBy('title', 'ASC');

        if ($language === 0) {
            $qb->andWhere($qb->expr()->eq('l10n_parent', $qb->createNamedParameter(0, ParameterType::INTEGER)));
        }

        if ($onlyFormUid !== null) {
            $qb->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($onlyFormUid, ParameterType::INTEGER)));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queryPages(int $formUid, int $language): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_powermail_domain_model_page');
        $qb->select('*')
            ->from('tx_powermail_domain_model_page')
            ->where($qb->expr()->eq('form', $qb->createNamedParameter($formUid, ParameterType::INTEGER)))
            ->andWhere($qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($language, ParameterType::INTEGER)))
            ->orderBy('sorting', 'ASC');

        if ($language === 0) {
            $qb->andWhere($qb->expr()->eq('l10n_parent', $qb->createNamedParameter(0, ParameterType::INTEGER)));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queryFields(int $pageUid, int $language): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_powermail_domain_model_field');
        $qb->select('*')
            ->from('tx_powermail_domain_model_field')
            ->where($qb->expr()->eq('page', $qb->createNamedParameter($pageUid, ParameterType::INTEGER)))
            ->andWhere($qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($language, ParameterType::INTEGER)))
            ->orderBy('sorting', 'ASC');

        if ($language === 0) {
            $qb->andWhere($qb->expr()->eq('l10n_parent', $qb->createNamedParameter(0, ParameterType::INTEGER)));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
