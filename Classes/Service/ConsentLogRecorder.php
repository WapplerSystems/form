<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Repository\ConsentLogRepository;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Writes the consent log.
 *
 * Why this exists at all: on a form whose only finisher sends an e-mail, that
 * mail is the entire trace of a submission. A mailbox is mutable, prunable and
 * says nothing about WHICH wording the visitor was shown, so it cannot carry
 * the demonstration Art. 7(1) GDPR asks for. This does, by recording per
 * submission: which consent, given or not, when, on which form and language,
 * and the SHA-256 of the exact text as rendered - with the text itself stored
 * once per distinct wording in a side table.
 *
 * Deliberately NOT part of EmailFinisher. Consent is a property of the
 * submission, not of a notification mail: a form that stores to the database
 * instead of mailing has exactly the same obligation, and a form with two
 * e-mail finishers must not record the consent twice.
 *
 * Off unless `featureConsentLog` is on, because a log carrying an e-mail
 * address is a processing decision a site has to take deliberately, together
 * with a retention window.
 *
 * @internal not part of public TYPO3 Core API
 */
class ConsentLogRecorder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Element identifiers tried, in order, when a form does not name a subject
     * field itself. The first one that exists and holds a value wins.
     */
    protected const SUBJECT_FIELD_GUESSES = ['email', 'e-mail', 'mail', 'emailaddress'];

    protected const SUBJECT_MAX_LENGTH = 255;

    /**
     * Submissions already written this request, so a second e-mail finisher
     * does not double the rows.
     *
     * @var array<string, true>
     */
    private array $recorded = [];

    /**
     * Set once a write failed in a way that will keep failing.
     */
    private bool $disabled = false;

    public function __construct(
        protected readonly ConsentLogRepository $repository,
        protected readonly ConsentElementResolver $consentElementResolver,
        protected readonly SubmissionIdProvider $submissionIdProvider,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly TranslationService $translationService,
    ) {}

    public function record(FinisherContext $finisherContext): void
    {
        if ($this->disabled || !$this->featureEnabled('featureConsentLog')) {
            return;
        }

        $formRuntime = $finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();
        $renderingOptions = $formDefinition->getRenderingOptions();

        // A form may opt out - a purely internal form behind a login has no
        // consent to demonstrate.
        if (($renderingOptions['consentLog']['enabled'] ?? true) === false) {
            return;
        }

        $submissionId = $this->submissionIdProvider->get();
        if (isset($this->recorded[$submissionId])) {
            return;
        }

        $consents = $this->consentElementResolver->findAll($formRuntime);
        if ($consents === []) {
            return;
        }
        // Claim the submission before the first write: if the loop dies
        // half-way, a retry would otherwise duplicate the rows it managed.
        $this->recorded[$submissionId] = true;

        $request = $finisherContext->getRequest();

        $languageUid = 0;
        $siteLanguage = $request->getAttribute('language');
        if ($siteLanguage instanceof SiteLanguage) {
            $languageUid = $siteLanguage->getLanguageId();
        }

        $siteIdentifier = '';
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $siteIdentifier = $site->getIdentifier();
        }

        $pageUid = 0;
        $routing = $request->getAttribute('routing');
        if ($routing !== null && method_exists($routing, 'getPageId')) {
            $pageUid = (int)$routing->getPageId();
        }

        [$subjectField, $subject] = $this->resolveSubject($formRuntime, $renderingOptions);
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());

        foreach ($consents as $consent) {
            $element = $consent['element'];
            $text = $this->resolveConsentText($element, $formRuntime);
            $textHash = hash('sha256', $text);

            $this->guard(function () use ($textHash, $text, $languageUid, $now): void {
                $this->repository->rememberText($textHash, $text, $languageUid, $now);
            });

            $this->guard(function () use (
                $now,
                $submissionId,
                $formDefinition,
                $element,
                $consent,
                $formRuntime,
                $textHash,
                $subject,
                $subjectField,
                $siteIdentifier,
                $pageUid,
                $languageUid
            ): void {
                $this->repository->insert([
                    'crdate' => $now,
                    'submission_id' => $submissionId,
                    'form_identifier' => $this->clip($formDefinition->getIdentifier(), 100),
                    'element_identifier' => $this->clip($element->getIdentifier(), 100),
                    'consent_key' => $this->clip($consent['key'], 100),
                    'given' => empty($formRuntime[$element->getIdentifier()]) ? 0 : 1,
                    'text_hash' => $textHash,
                    'subject' => $subject,
                    'subject_field' => $this->clip($subjectField, 100),
                    'site_identifier' => $this->clip($siteIdentifier, 100),
                    'page_uid' => $pageUid,
                    'language_uid' => $languageUid,
                ]);
            });
        }
    }

    /**
     * The consent wording as the visitor saw it.
     *
     * NOT $element->getLabel(): that returns the raw definition value, which on
     * a multi-language site is the default-language text. A German visitor
     * would then be on record as having agreed to the English paragraph - a
     * consent record showing the wrong wording is worse than none, because it
     * looks authoritative. The translation overlay is resolved with no explicit
     * locale, so the active site language wins, exactly as during rendering.
     */
    protected function resolveConsentText(RenderableInterface $element, FormRuntime $formRuntime): string
    {
        $label = $this->translationService->translateFormElementValue($element, ['label'], $formRuntime);

        return is_string($label) ? $label : (string)$element->getLabel();
    }

    /**
     * The value that lets a record be produced for a named person, and the
     * element it came from.
     *
     * A form can name the field explicitly through
     * `renderingOptions.consentLog.subjectField`; otherwise the usual e-mail
     * identifiers are tried. If none of them exists the log still records the
     * consent - anonymously, which is worth more than nothing and avoids
     * guessing a random text field into an evidence column.
     *
     * @param array<string, mixed> $renderingOptions
     * @return array{0: string, 1: string}
     */
    protected function resolveSubject(FormRuntime $formRuntime, array $renderingOptions): array
    {
        $candidates = $renderingOptions['consentLog']['subjectField'] ?? null;
        if (is_string($candidates) && $candidates !== '') {
            $candidates = GeneralUtility::trimExplode(',', $candidates, true);
        }
        if (!is_array($candidates) || $candidates === []) {
            $candidates = self::SUBJECT_FIELD_GUESSES;
        }

        foreach ($candidates as $identifier) {
            $identifier = (string)$identifier;
            $value = $formRuntime[$identifier] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return [$identifier, $this->clip(trim($value), self::SUBJECT_MAX_LENGTH)];
            }
        }

        return ['', ''];
    }

    protected function featureEnabled(string $key, bool $default = false): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', $key);
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return $default;
        }
    }

    protected function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    /**
     * Runs a write and swallows what it must.
     *
     * Same trade as the mail log: a missing table is a deployment state, and
     * losing a log row is always preferable to losing the submission it
     * describes. A consent that is not recorded is a gap in the evidence, not a
     * reason to reject the visitor's enquiry.
     */
    protected function guard(callable $write): void
    {
        try {
            $write();
        } catch (TableNotFoundException $e) {
            $this->disabled = true;
            $this->logger?->warning(
                'Consent log table is missing, disabling the recorder for this request. Run the database schema update.',
                ['exception' => $e]
            );
        } catch (\Throwable $e) {
            $this->logger?->warning('Could not write the consent log row', ['exception' => $e]);
        }
    }
}
