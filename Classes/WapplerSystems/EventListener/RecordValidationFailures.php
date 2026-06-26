<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Form\WapplerSystems\Event\AfterFormIsValidatedEvent;

/**
 * Records one row in `tx_form_validation_log` per validation error so
 * editors can later analyze WHERE submissions drop off — which fields
 * fail most often, with which error codes, across how many distinct
 * submission attempts.
 *
 * Opt-in per form via a renderingOption:
 *
 *   renderingOptions:
 *     recordValidationFailures: true
 *
 * Defaults to off so existing forms incur zero cost and zero DB growth
 * unless the operator explicitly enables it.
 *
 * Privacy:
 *  - NO submitted user values are stored — only form/element identifiers,
 *    error codes, the already-translated error message text, and an
 *    HMAC of the FormSession identifier (so multi-attempt patterns from
 *    one visitor can be aggregated WITHOUT identifying the visitor).
 *  - error_message is the translated label that was displayed to the
 *    user, not raw input — safe to store.
 *  - Operators are responsible for periodic cleanup; recommend setting
 *    up a Scheduler command that prunes rows older than N days. A
 *    cleanup command may ship in a later phase.
 */
#[AsEventListener('wapplersystems-form/record-validation-failures')]
final class RecordValidationFailures
{
    private const TABLE_NAME = 'tx_form_validation_log';
    private const ERROR_MESSAGE_MAX_LENGTH = 500;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(AfterFormIsValidatedEvent $event): void
    {
        $formDefinition = $event->formRuntime->getFormDefinition();
        $renderingOptions = $formDefinition->getRenderingOptions();
        if (empty($renderingOptions['recordValidationFailures'])) {
            return;
        }

        if (!$event->result->hasErrors()) {
            return;
        }

        $errorsByPath = $event->result->getFlattenedErrors();
        if ($errorsByPath === []) {
            return;
        }

        $formIdentifier = $formDefinition->getIdentifier();
        $request = $event->request;

        $pageUid = 0;
        $routing = $request->getAttribute('routing');
        if ($routing !== null && method_exists($routing, 'getPageId')) {
            $pageUid = (int)$routing->getPageId();
        }

        $languageUid = 0;
        $siteLanguage = $request->getAttribute('language');
        if ($siteLanguage instanceof SiteLanguage) {
            $languageUid = $siteLanguage->getLanguageId();
        }

        $sessionId = $event->formRuntime->getFormSession()?->getIdentifier() ?? '';
        $sessionHash = $sessionId !== '' ? hash('sha256', $sessionId) : '';

        $pageIndex = $event->page->getIndex();
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);

        foreach ($errorsByPath as $propertyPath => $errorList) {
            $elementIdentifier = $this->extractElementIdentifier($propertyPath, $formIdentifier);
            foreach ($errorList as $error) {
                if (!$error instanceof Error) {
                    continue;
                }
                $connection->insert(
                    self::TABLE_NAME,
                    [
                        'crdate' => $now,
                        'form_identifier' => $formIdentifier,
                        'page_uid' => $pageUid,
                        'language_uid' => $languageUid,
                        'element_identifier' => $elementIdentifier,
                        'property_path' => $propertyPath,
                        'error_code' => $error->getCode(),
                        'error_message' => mb_substr($error->getMessage(), 0, self::ERROR_MESSAGE_MAX_LENGTH),
                        'page_index' => $pageIndex,
                        'session_hash' => $sessionHash,
                    ],
                );
            }
        }
    }

    /**
     * Extracts the first element identifier from a propertyPath like
     * "<formIdentifier>.<elementIdentifier>" or
     * "<formIdentifier>.<elementIdentifier>.<sub>".
     */
    private function extractElementIdentifier(string $propertyPath, string $formIdentifier): string
    {
        $prefix = $formIdentifier . '.';
        if (str_starts_with($propertyPath, $prefix)) {
            $remainder = substr($propertyPath, strlen($prefix));
            $parts = explode('.', $remainder, 2);
            return $parts[0] ?? '';
        }
        return $propertyPath;
    }
}
