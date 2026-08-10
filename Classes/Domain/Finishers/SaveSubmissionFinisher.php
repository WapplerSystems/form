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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Service\SubmissionValueSerializer;

/**
 * Persists a complete form submission into `tx_form_submission` so editors can
 * review and export entries in the "Form submissions" backend module. Opt-in
 * per form: the editor adds this finisher explicitly, which keeps storage of
 * personal data an intentional decision (GDPR).
 *
 * All submitted values are stored as JSON in the `content` column, together
 * with a snapshot of the field labels (`field_labels`) so exports keep stable,
 * human-readable headers even after the form definition is later changed.
 *
 * Options:
 *   storagePid  - page the record is stored on; 0 (default) uses the page that
 *                 renders the form.
 *   storeUploads - include uploaded-file references in the payload (default true).
 *   storeIpHash  - store a sha256 hash of the client IP (default false; the raw
 *                  address is never stored).
 *   label        - overrides the stored form label; defaults to the form's own label.
 */
class SaveSubmissionFinisher extends AbstractFinisher
{
    private const TABLE_NAME = 'tx_form_submission';

    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'storagePid' => 0,
        'storeUploads' => true,
        'storeIpHash' => false,
        'label' => '',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SubmissionValueSerializer $serializer,
    ) {}

    protected function executeInternal(): void
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $storeUploads = (bool)$this->parseOption('storeUploads');
        $storeIpHash = (bool)$this->parseOption('storeIpHash');

        $labelOption = (string)$this->parseOption('label');
        $formLabel = $labelOption !== '' ? $labelOption : (string)$formDefinition->getLabel();

        $request = $this->finisherContext->getRequest();
        $pageUid = 0;
        $routing = $request->getAttribute('routing');
        if ($routing !== null && method_exists($routing, 'getPageId')) {
            $pageUid = (int)$routing->getPageId();
        }

        $storagePid = (int)$this->parseOption('storagePid');
        if ($storagePid <= 0) {
            $storagePid = $pageUid;
        }

        $languageUid = $formRuntime->getCurrentSiteLanguage()?->getLanguageId() ?? 0;

        $values = $this->serializer->serializeValues(
            $this->finisherContext->getFormValues(),
            $storeUploads,
        );
        $fieldLabels = $this->serializer->extractFieldLabels($formRuntime);

        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $connection->insert(
            self::TABLE_NAME,
            [
                'pid' => $storagePid,
                'crdate' => $now,
                'tstamp' => $now,
                'form_identifier' => $formDefinition->getIdentifier(),
                'form_label' => mb_substr($formLabel, 0, 255),
                'page_uid' => $pageUid,
                'language_uid' => $languageUid,
                'content' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'field_labels' => json_encode($fieldLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_hash' => $storeIpHash ? $this->hashClientIp() : '',
                'session_hash' => $this->sessionHash(),
            ],
        );

        // Expose the inserted uid so downstream finishers can reference it via
        // {SaveSubmission.insertedUid}.
        $this->finisherContext->getFinisherVariableProvider()->add(
            'SaveSubmission',
            'insertedUid',
            (int)$connection->lastInsertId(),
        );
    }

    private function hashClientIp(): string
    {
        $ip = (string)GeneralUtility::getIndpEnv('REMOTE_ADDR');
        return $ip !== '' ? hash('sha256', $ip) : '';
    }

    private function sessionHash(): string
    {
        $sessionId = $this->finisherContext->getFormRuntime()->getFormSession()?->getIdentifier() ?? '';
        return $sessionId !== '' ? hash('sha256', $sessionId) : '';
    }
}
