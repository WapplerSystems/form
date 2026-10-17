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
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\FormValueResolver;
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
 *   storeOptionLabels - store the (translated) label of select, radio and
 *                  checkbox-group options instead of their key (default true),
 *                  so the module and its exports read like the notification
 *                  mail ("Nordrhein-Westfalen", not "NW").
 *   excludeTypes - element types that carry no visitor input and are therefore
 *                  not stored (default: StaticText, ContentElement, Honeypot).
 *
 * Only what the visitor actually answered is stored: elements that are
 * disabled (directly, by a variant, or through a disabled parent) and
 * composite elements (pages, sections, grid rows) are skipped, as are the
 * types in `excludeTypes` - a honeypot would otherwise end up as a column with
 * a random identifier in every export. Values of Date/DatePicker elements are
 * stored as a plain date: the property mapper fills in the current time of day,
 * which an ISO timestamp would then present as if it had been entered.
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
        'storeOptionLabels' => true,
        'excludeTypes' => ['StaticText', 'ContentElement', 'Honeypot'],
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SubmissionValueSerializer $serializer,
        private readonly FormValueResolver $formValueResolver,
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

        // The FormEngine group field (flexform override) yields "pages_<uid>".
        $storagePid = (int)str_replace('pages_', '', (string)$this->parseOption('storagePid'));
        if ($storagePid <= 0) {
            $storagePid = $pageUid;
        }

        $languageUid = $formRuntime->getCurrentSiteLanguage()?->getLanguageId() ?? 0;

        $excludeTypes = $this->parseOption('excludeTypes');
        $excludeTypes = is_array($excludeTypes) ? array_map('strval', $excludeTypes) : [];

        $storeOptionLabels = (bool)$this->parseOption('storeOptionLabels');
        $formValues = $this->finisherContext->getFormValues();
        $storedValues = [];
        $fieldLabels = [];
        foreach ($this->getStorableElements($formRuntime, $excludeTypes) as $element) {
            $identifier = $element->getIdentifier();
            if (!array_key_exists($identifier, $formValues)) {
                continue;
            }
            $value = $formValues[$identifier];
            if ($storeOptionLabels && is_array($element->getProperties()['options'] ?? null)) {
                $value = $this->formValueResolver->resolveDisplayValue($element, $value, $formRuntime);
            }
            $storedValues[$identifier] = $this->formatDateValue($element, $value);
            $label = (string)$element->getLabel();
            $fieldLabels[$identifier] = $label !== '' ? $label : $identifier;
        }
        $values = $this->serializer->serializeValues($storedValues, $storeUploads);

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

    /**
     * @param list<string> $excludeTypes
     * @return list<FormElementInterface>
     */
    private function getStorableElements(FormRuntime $formRuntime, array $excludeTypes): array
    {
        $elements = [];
        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $renderable) {
            if (!$renderable instanceof FormElementInterface) {
                continue;
            }
            if (($renderable->getRenderingOptions()['_isCompositeFormElement'] ?? false)
                || in_array($renderable->getType(), $excludeTypes, true)
                || !$this->isEnabledWithParents($renderable)
            ) {
                continue;
            }
            $elements[] = $renderable;
        }
        return $elements;
    }

    private function isEnabledWithParents(RenderableInterface $renderable): bool
    {
        do {
            if (!$renderable->isEnabled()) {
                return false;
            }
        } while (($renderable = $renderable->getParentRenderable()) instanceof RenderableInterface);
        return true;
    }

    private function formatDateValue(FormElementInterface $element, mixed $value): mixed
    {
        if (!$value instanceof \DateTimeInterface) {
            return $value;
        }
        return match ($element->getType()) {
            'Date' => $value->format('Y-m-d'),
            'DatePicker' => $value->format((string)($element->getProperties()['dateFormat'] ?? 'Y-m-d')),
            default => $value,
        };
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
