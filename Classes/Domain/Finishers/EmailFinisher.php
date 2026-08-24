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

namespace TYPO3\CMS\Form\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\TemplatedEmailFactory;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Model\FormElements\FileUpload;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\BeforeEmailFinisherInitializedEvent;
use TYPO3\CMS\Form\ViewHelpers\RenderRenderableViewHelper;
// WapplerSystems fork additions:
use TYPO3\CMS\Form\Event\AfterMailSentEvent;
use TYPO3\CMS\Form\Event\MailBeforeSendingEvent;
use TYPO3\CMS\Form\Service\ConsentElementResolver;

/**
 * This finisher sends an email to one recipient
 *
 * Options:
 *
 * - templateName (mandatory): Template name for the mail body
 * - templateRootPaths: root paths for the templates
 * - layoutRootPaths: root paths for the layouts
 * - partialRootPaths: root paths for the partials
 * - variables: associative array of variables which are available inside the Fluid template
 *
 * The following options control the mail sending. In all of them, placeholders in the form
 * of {...} are replaced with the corresponding form value; i.e. {email} as senderAddress
 * makes the recipient address configurable.
 *
 * - subject (mandatory): Subject of the email
 * - recipients (mandatory): Email addresses and human-readable names of the recipients
 * - senderAddress (mandatory): Email address of the sender
 * - senderName: Human-readable name of the sender
 * - replyToRecipients: Email addresses and human-readable names of the reply-to recipients
 * - carbonCopyRecipients: Email addresses and human-readable names of the copy recipients
 * - blindCarbonCopyRecipients: Email addresses and human-readable names of the blind copy recipients
 * - title: The title of the email - If not set "subject" is used by default
 *
 * WapplerSystems fork additions:
 *
 * - hideConsentFields (default true): omits consent checkboxes (DSGVO / privacy
 *   policy) from the rendered field list. Their labels are whole paragraphs of
 *   legal text that push the actual enquiry out of sight.
 * - consentSummary (default true): replaces them with one compact row naming
 *   each consent and whether it was given, so the mail still documents the
 *   consent instead of dropping it. Ignored when hideConsentFields is off,
 *   because then the full rows are in the table anyway.
 * - consentSummaryLabel: overrides the label of that row.
 * - excludeElements: further element identifiers to omit, either as an array or
 *   as a comma-separated list.
 * - showFormLanguage (default false): adds a row naming the site language the
 *   form was filled in with. Useful when "translation.language" pins the mail to
 *   one language for the service desk, but they still need to know which
 *   language version the visitor used.
 * - formLanguageLabel: overrides the label of that row.
 *
 * Elements that cannot carry a submitted value (StaticText, ContentElement,
 * Honeypot) are always omitted - the templates only ever printed their label
 * followed by a dash.
 *
 * Scope: frontend
 */
class EmailFinisher extends AbstractFinisher
{
    /**
     * Element types that never hold a submitted value.
     */
    protected const VALUELESS_ELEMENT_TYPES = [
        'StaticText',
        'ContentElement',
        'Honeypot',
    ];

    /**
     * Longest short name the summary prints before it falls back to an
     * ellipsis - a consent label is a paragraph, not a caption.
     */
    protected const CONSENT_SHORT_LABEL_LENGTH = 60;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'recipientName' => '',
        'senderName' => '',
        'addHtmlPart' => true,
        'attachUploads' => true,
        'hideConsentFields' => true,
        'consentSummary' => true,
        'showFormLanguage' => false,
        'excludeElements' => '',
    ];

    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly TemplatedEmailFactory $templatedEmailFactory,
        protected readonly MailerInterface $mailer,
    ) {}

    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @throws FinisherException
     */
    protected function executeInternal(): void
    {
        $this->options = $this->eventDispatcher
            ->dispatch(new BeforeEmailFinisherInitializedEvent($this->finisherContext, $this->options))
            ->getOptions();
        // Flexform overrides write strings instead of integers so
        // we need to cast the string '0' to false.
        if (
            isset($this->options['addHtmlPart'])
            && $this->options['addHtmlPart'] === '0'
        ) {
            $this->options['addHtmlPart'] = false;
        }

        $subject = (string)$this->parseOption('subject');
        $recipients = $this->getRecipients('recipients');
        $senderAddress = $this->parseOption('senderAddress');
        $senderAddress = is_string($senderAddress) ? $senderAddress : '';
        $senderName = $this->parseOption('senderName');
        $senderName = is_string($senderName) ? $senderName : '';
        $replyToRecipients = $this->getRecipients('replyToRecipients');
        $carbonCopyRecipients = $this->getRecipients('carbonCopyRecipients');
        $blindCarbonCopyRecipients = $this->getRecipients('blindCarbonCopyRecipients');
        $addHtmlPart = (bool)$this->parseOption('addHtmlPart');
        $attachUploads = $this->parseOption('attachUploads');
        $title = (string)$this->parseOption('title') ?: $subject;

        if ($subject === '') {
            throw new FinisherException('The option "subject" must be set for the EmailFinisher.', 1327060320);
        }
        if (empty($recipients)) {
            throw new FinisherException('The option "recipients" must be set for the EmailFinisher.', 1327060200);
        }
        if (empty($senderAddress)) {
            throw new FinisherException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210);
        }

        $formRuntime = $this->finisherContext->getFormRuntime();

        $mail = $this
            ->initializeFluidEmail($formRuntime)
            ->from(new Address($senderAddress, $senderName))
            ->to(...$recipients)
            ->subject($subject)
            ->format($addHtmlPart ? FluidEmail::FORMAT_BOTH : FluidEmail::FORMAT_PLAIN)
            ->assign('title', $title);

        if (!empty($replyToRecipients)) {
            $mail->replyTo(...$replyToRecipients);
        }

        if (!empty($carbonCopyRecipients)) {
            $mail->cc(...$carbonCopyRecipients);
        }

        if (!empty($blindCarbonCopyRecipients)) {
            $mail->bcc(...$blindCarbonCopyRecipients);
        }

        if (is_string($this->options['translation']['language'] ?? null) && $this->options['translation']['language'] !== '') {
            $mail->assign('languageKey', $this->options['translation']['language']);
        }

        // WapplerSystems fork: consent checkboxes and valueless display
        // elements are dropped from the field list, see the class doc block.
        $analysis = $this->analyseElements($formRuntime);
        $mail->assign('excludedElementIdentifiers', $analysis['excluded']);

        // Dropping a consent checkbox must not drop the evidence that it was
        // ticked - Art. 7(1) GDPR asks the controller to be able to
        // demonstrate the consent. One compact row does that without the
        // paragraph of legal text.
        if ($analysis['consent'] !== []) {
            $mail->assignMultiple([
                'consentSummaryLabel' => $this->getConsentSummaryLabel(),
                'consentSummary' => $analysis['consent'],
            ]);
        }

        // WapplerSystems fork: "translation.language" pins the mail to one
        // language for the recipient - this keeps the language the visitor
        // actually used visible.
        if ($this->getBooleanOption('showFormLanguage')) {
            $siteLanguage = $this->finisherContext->getRequest()->getAttribute('language');
            if ($siteLanguage instanceof SiteLanguage) {
                $mail->assignMultiple([
                    'formLanguageLabel' => $this->getFormLanguageLabel(),
                    'formLanguage' => $siteLanguage->getTitle(),
                    'formLanguageCode' => $siteLanguage->getLocale()->getName(),
                ]);
            }
        }

        $message = $this->parseOption('message');
        if (is_string($message) && $message !== '') {
            // Remove whitespace between HTML tags to prevent lib.parseFunc_RTE
            // from converting newlines into additional blank lines in the email output
            $message = preg_replace('/>\s+</', '><', $message);
            $placeholderPos = strpos($message, '{formValues}');
            if ($placeholderPos !== false) {
                $mail->assign('messageBefore', substr($message, 0, $placeholderPos));
                $mail->assign('messageAfter', substr($message, $placeholderPos + strlen('{formValues}')));
            } else {
                // No placeholder - show message only, no form values
                $mail->assign('messageBefore', $message);
                $mail->assign('messageAfter', '');
                $mail->assign('hideFormValues', true);
            }
        }

        // WapplerSystems fork (Feature 3): dedicated plain-text body.
        // If "plainMessage" is set, the plain-text mail part uses it verbatim
        // (split around the {formValues} placeholder, just like the HTML body).
        // If it is empty, the plain-text template falls back to the stripped
        // HTML "message" (legacy behaviour) — so existing forms are unaffected.
        $plainMessage = $this->parseOption('plainMessage');
        if (is_string($plainMessage) && $plainMessage !== '') {
            $mail->assign('plainMessageProvided', true);
            $placeholderPos = strpos($plainMessage, '{formValues}');
            if ($placeholderPos !== false) {
                $mail->assign('plainMessageBefore', substr($plainMessage, 0, $placeholderPos));
                $mail->assign('plainMessageAfter', substr($plainMessage, $placeholderPos + strlen('{formValues}')));
            } else {
                // No placeholder - show plain message only, no form values
                $mail->assign('plainMessageBefore', $plainMessage);
                $mail->assign('plainMessageAfter', '');
                $mail->assign('hidePlainFormValues', true);
            }
        }

        if ($attachUploads) {
            foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
                if (!$element instanceof FileUpload) {
                    continue;
                }
                $file = $formRuntime[$element->getIdentifier()];
                if ($file instanceof FileReference) {
                    $file = $file->getOriginalResource();
                }
                if ($file instanceof FileInterface) {
                    $mail->attach($file->getContents(), $file->getName(), $file->getMimeType());
                } elseif ($file instanceof ObjectStorage) {
                    foreach ($file as $singleFile) {
                        if ($singleFile instanceof FileReference) {
                            $singleFile = $singleFile->getOriginalResource();
                        }
                        if ($singleFile instanceof FileInterface) {
                            $mail->attach($singleFile->getContents(), $singleFile->getName(), $singleFile->getMimeType());
                        }
                    }
                }
            }
        }

        // WapplerSystems fork: listeners can mutate the FluidEmail (recipients,
        // headers, attachments) before transport. Dispatched after FluidEmail
        // is fully populated, before the mailer transport runs.
        $this->eventDispatcher->dispatch(
            new MailBeforeSendingEvent($mail, $this->finisherContext, $this),
        );

        try {
            $this->mailer->send($mail);
        } catch (TransportExceptionInterface $e) {
            throw new FinisherException(
                'Failed to send the email: ' . $e->getMessage(),
                1754047320,
                $e
            );
        }

        // WapplerSystems fork: fires only after a successful transport — the
        // reliable hook for "delivered" audit logging / post-delivery actions.
        $this->eventDispatcher->dispatch(
            new AfterMailSentEvent($mail, $this->finisherContext, $this),
        );
    }

    protected function initializeFluidEmail(FormRuntime $formRuntime): FluidEmail
    {
        $mailMessage = $this->templatedEmailFactory->createWithOverrides(
            $this->options['templateRootPaths'] ?? [],
            $this->options['layoutRootPaths'] ?? [],
            $this->options['partialRootPaths'] ?? [],
            $this->finisherContext->getRequest(),
        );

        if (!isset($this->options['templateName']) || $this->options['templateName'] === '') {
            throw new FinisherException('The option "templateName" must be set to use FluidEmail.', 1599834020);
        }

        // Migrate old template name to default FluidEmail name
        if ($this->options['templateName'] === '{@format}.html') {
            $this->options['templateName'] = 'Default';
        }

        $mailMessage
            ->setTemplate($this->options['templateName'])
            ->assignMultiple([
                'finisherVariableProvider' => $this->finisherContext->getFinisherVariableProvider(),
                'form' => $formRuntime,
            ]);

        if (is_array($this->options['variables'] ?? null)) {
            $mailMessage->assignMultiple($this->options['variables']);
        }

        $mailMessage
            ->getViewHelperVariableContainer()
            ->addOrUpdate(RenderRenderableViewHelper::class, 'formRuntime', $formRuntime);

        return $mailMessage;
    }

    /**
     * WapplerSystems fork: walks the form once and decides per element what
     * the e-mail templates do with it - skip it entirely, or fold it into the
     * consent summary.
     *
     * @return array{excluded: string[], consent: array<int, array{name: string, given: bool}>}
     */
    protected function analyseElements(FormRuntime $formRuntime): array
    {
        $configured = $this->parseOption('excludeElements') ?? '';
        if (is_string($configured)) {
            $configured = GeneralUtility::trimExplode(',', $configured, true);
        }
        $excluded = array_map(strval(...), (array)$configured);
        $consent = [];

        $hideConsentFields = $this->getBooleanOption('hideConsentFields');
        // With the full rows still in the table, a summary would only repeat
        // them.
        $summariseConsent = $hideConsentFields && $this->getBooleanOption('consentSummary');

        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
            $identifier = $element->getIdentifier();

            if (in_array($element->getType(), self::VALUELESS_ELEMENT_TYPES, true)) {
                $excluded[] = $identifier;
                continue;
            }
            if (!$hideConsentFields) {
                continue;
            }

            $consentKey = $this->getConsentElementResolver()->resolveConsentKey($element);
            if ($consentKey === null) {
                continue;
            }

            $excluded[] = $identifier;
            if ($summariseConsent) {
                $consent[] = [
                    'name' => $this->getConsentName($element, $consentKey),
                    'given' => !empty($formRuntime[$identifier]),
                ];
            }
        }

        return [
            'excluded' => array_values(array_unique($excluded)),
            'consent' => $consent,
        ];
    }

    /**
     * WapplerSystems fork: shared with the consent log, so the mail cannot
     * summarise a consent the log did not record.
     *
     * Reached through makeInstance() rather than the constructor: extensions in
     * the wild subclass this finisher and call parent::__construct() with its
     * current three arguments.
     */
    protected function getConsentElementResolver(): ConsentElementResolver
    {
        return GeneralUtility::makeInstance(ConsentElementResolver::class);
    }

    /**
     * WapplerSystems fork: short name of a single consent. The element label
     * is a paragraph of legal text, so a recognised consent kind gets a
     * caption from the fork's XLF; anything else falls back to the truncated
     * label, which at least stays recognisable.
     */
    protected function getConsentName(RenderableInterface $element, string $consentKey): string
    {
        $name = $this->translateForMailLanguage('finisher.email.consent.' . $consentKey);
        if ($name !== null && $name !== '') {
            return $name;
        }

        $label = trim(strip_tags((string)$element->getLabel()));
        if ($label === '') {
            return $element->getIdentifier();
        }
        if (mb_strlen($label) <= self::CONSENT_SHORT_LABEL_LENGTH) {
            return $label;
        }

        return rtrim(mb_substr($label, 0, self::CONSENT_SHORT_LABEL_LENGTH), " \t\n\r,;:.") . '…';
    }

    /**
     * WapplerSystems fork: label of the consent summary row.
     */
    protected function getConsentSummaryLabel(): string
    {
        if (is_string($this->options['consentSummaryLabel'] ?? null) && $this->options['consentSummaryLabel'] !== '') {
            return (string)$this->parseOption('consentSummaryLabel');
        }

        return $this->translateForMailLanguage('finisher.email.consent') ?? 'Consent';
    }

    /**
     * WapplerSystems fork: label of the "form language" row. An explicit
     * option wins, otherwise the fork label is resolved in the language the
     * mail itself is rendered in.
     */
    protected function getFormLanguageLabel(): string
    {
        if (is_string($this->options['formLanguageLabel'] ?? null) && $this->options['formLanguageLabel'] !== '') {
            return (string)$this->parseOption('formLanguageLabel');
        }

        return $this->translateForMailLanguage('finisher.email.formLanguage') ?? 'Language';
    }

    /**
     * WapplerSystems fork: resolves a fork label in the language the mail is
     * rendered in - "translation.language" if it pins one, the current site
     * language otherwise.
     */
    protected function translateForMailLanguage(string $key): ?string
    {
        $languageKey = $this->options['translation']['language'] ?? null;

        return LocalizationUtility::translate(
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:' . $key,
            null,
            [],
            is_string($languageKey) && $languageKey !== '' ? $languageKey : null,
        );
    }

    /**
     * WapplerSystems fork: FlexForm overrides write strings, so "0" has to be
     * read as false the same way "addHtmlPart" already does.
     */
    protected function getBooleanOption(string $name): bool
    {
        // parseOption() is what falls back to $defaultOptions - reading
        // $this->options directly would ignore the default.
        $value = $this->parseOption($name);
        if ($value === '0' || $value === '') {
            return false;
        }
        return (bool)$value;
    }

    protected function getRecipients(string $listOption): array
    {
        $recipients = $this->parseOption($listOption) ?? [];
        if (!is_array($recipients) || $recipients === []) {
            return [];
        }

        $addresses = [];
        foreach ($recipients as $address => $name) {
            // The if is needed to set address and name with TypoScript
            if (MathUtility::canBeInterpretedAsInteger($address)) {
                if (is_array($name)) {
                    $address = $name[0] ?? '';
                    $name = $name[1] ?? '';
                } else {
                    $address = $name;
                    $name = '';
                }
            }

            $address = trim((string)$address);

            if (!GeneralUtility::validEmail($address)) {
                // Drop entries without a valid address
                continue;
            }
            $addresses[] = new Address($address, $name);
        }
        return $addresses;
    }
}
