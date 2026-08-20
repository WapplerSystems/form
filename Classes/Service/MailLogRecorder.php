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
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Form\Domain\DTO\MailLogPolicy;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Enum\MailLogStatus;

/**
 * Holds the mail log's per-request state and decides what may be written.
 *
 * A row is opened before the finisher runs and advanced as its outcome becomes
 * known. That ordering is the whole point: the two failures worth catching —
 * a missing `senderAddress`, thrown before any mail object exists, and a hard
 * abort, which throws nothing at all — would leave no trace whatsoever if the
 * row were only written once the outcome was known.
 *
 * State lives here rather than in the listener so it can be injected and mocked,
 * and so the repository stays reusable by the module, the cleanup task and the
 * CLI check. The service is shared, and TYPO3 builds one container per request,
 * which makes "shared" and "request-scoped" the same thing here.
 *
 * This class must never be able to break a form submission. Every write is
 * guarded, and a missing table (schema not yet applied after an update)
 * disables the recorder for the rest of the request instead of throwing into
 * the middle of someone's contact form.
 */
class MailLogRecorder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * uid of the open row per finisher instance. Keyed by spl_object_id rather
     * than by identifier because one form can carry two Email finishers, and
     * each needs its own row.
     *
     * @var array<int, int>
     */
    private array $openRows = [];

    /**
     * @var array<int, MailLogPolicy>
     */
    private array $policies = [];

    private ?string $submissionId = null;

    /**
     * Set once a write failed in a way that will keep failing.
     */
    private bool $disabled = false;

    public function __construct(
        private readonly MailLogRepository $repository,
        private readonly MailLogRecipientFormatter $recipientFormatter,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly MailerInterface $mailer,
    ) {}

    /**
     * Opens a row for a mail that is about to be attempted.
     */
    public function open(EmailFinisher $finisher, FinisherContext $finisherContext): void
    {
        if ($this->disabled) {
            return;
        }

        $formRuntime = $finisherContext->getFormRuntime();
        $policy = MailLogPolicy::resolve(
            $formRuntime->getFormDefinition()->getRenderingOptions(),
            $finisher->getOptions(),
            $this->featureEnabled('featureMailLog'),
            $this->featureEnabled('mailLogAllForms', true),
        );

        $key = spl_object_id($finisher);
        $this->policies[$key] = $policy;

        if (!$policy->enabled) {
            return;
        }

        $request = $finisherContext->getRequest();
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

        $siteIdentifier = '';
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $siteIdentifier = $site->getIdentifier();
        }

        $uid = $this->guard(fn(): int => $this->repository->open([
            // EXEC_TIME for crdate keeps this consistent with the validation
            // log; tstamp uses real time() so the send latency is not always 0.
            'crdate' => (int)($GLOBALS['EXEC_TIME'] ?? time()),
            'tstamp' => 0,
            'status' => MailLogStatus::PENDING->value,
            'submission_id' => $this->submissionId(),
            'form_identifier' => $this->clip($formRuntime->getFormDefinition()->getIdentifier(), 100),
            'finisher_identifier' => $this->clip($finisher->getFinisherIdentifier(), 100),
            'finisher_class' => $this->clip($finisher::class, 190),
            'site_identifier' => $this->clip($siteIdentifier, 100),
            'page_uid' => $pageUid,
            'language_uid' => $languageUid,
            'recipient_mode' => $policy->recipientMode,
        ]));

        if ($uid !== null) {
            $this->openRows[$key] = $uid;
        }
    }

    /**
     * Enriches the open row once the mail object exists. Everything identifying
     * enters the log here, and only as far as the policy allows.
     */
    public function prepare(FluidEmail $mail, EmailFinisher $finisher): void
    {
        $uid = $this->openRows[spl_object_id($finisher)] ?? null;
        if ($uid === null) {
            return;
        }
        $policy = $this->policies[spl_object_id($finisher)] ?? MailLogPolicy::disabled();

        [$recipients, $recipientCount] = $this->recipientFormatter->format(
            $this->addresses($mail->getTo()),
            $policy->recipientMode,
        );

        $fields = [
            'status' => MailLogStatus::PREPARED->value,
            'recipients' => $recipients,
            'recipient_count' => $recipientCount,
            // Names the boundary "SENT" actually marks: with a spool transport
            // that is "queued", with the null transport it is "discarded".
            'transport' => $this->clip($this->transportName(), 50),
            'attachment_count' => count($mail->getAttachments()),
        ];

        if ($policy->logSubject) {
            $fields['subject'] = $this->clip((string)$mail->getSubject(), 255);
        }
        if ($policy->logSender) {
            [$sender] = $this->recipientFormatter->format($this->addresses($mail->getFrom()), $policy->recipientMode);
            $fields['sender'] = $sender;
        }
        if ($policy->logReplyTo) {
            [$replyTo] = $this->recipientFormatter->format($this->addresses($mail->getReplyTo()), $policy->recipientMode);
            $fields['reply_to'] = $replyTo;
        }

        $this->guard(fn(): int => $this->update($uid, $fields));
    }

    /**
     * Closes the row after the transport accepted the mail.
     */
    public function sent(EmailFinisher $finisher): void
    {
        $key = spl_object_id($finisher);
        $uid = $this->openRows[$key] ?? null;
        if ($uid === null) {
            return;
        }

        $this->guard(fn(): int => $this->update($uid, [
            'status' => MailLogStatus::SENT->value,
            'tstamp' => time(),
            'message_id' => $this->clip($this->messageId(), 190),
        ]));

        unset($this->openRows[$key], $this->policies[$key]);
    }

    /**
     * Closes the row after a reported failure.
     *
     * Writes a standalone row when none is open. That happens when the failure
     * was thrown before the row could be opened, and a failure without a record
     * is exactly what this feature exists to prevent.
     */
    public function failed(EmailFinisher $finisher, FinisherContext $finisherContext, FinisherException $exception): void
    {
        if ($this->disabled) {
            return;
        }

        $key = spl_object_id($finisher);
        $policy = $this->policies[$key] ?? null;
        if ($policy === null) {
            // open() never ran for this finisher, so resolve the policy now.
            $policy = MailLogPolicy::resolve(
                $finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions(),
                $finisher->getOptions(),
                $this->featureEnabled('featureMailLog'),
                $this->featureEnabled('mailLogAllForms', true),
            );
        }
        if (!$policy->enabled) {
            return;
        }

        $code = (int)$exception->getCode();
        $fields = [
            'status' => MailLogStatus::FAILED->value,
            'tstamp' => time(),
            'error_code' => $code,
            'error_class' => $this->clip($exception::class, 190),
            'error_message' => $policy->mayStoreErrorMessage($code)
                ? $this->clip($exception->getMessage(), 500)
                : '',
        ];

        $uid = $this->openRows[$key] ?? null;
        if ($uid !== null) {
            $this->guard(fn(): int => $this->update($uid, $fields));
            unset($this->openRows[$key], $this->policies[$key]);
            return;
        }

        $this->guard(fn(): int => $this->repository->open($fields + [
            'crdate' => (int)($GLOBALS['EXEC_TIME'] ?? time()),
            'submission_id' => $this->submissionId(),
            'form_identifier' => $this->clip($finisherContext->getFormRuntime()->getFormDefinition()->getIdentifier(), 100),
            'finisher_identifier' => $this->clip($finisher->getFinisherIdentifier(), 100),
            'finisher_class' => $this->clip($finisher::class, 190),
            'recipient_mode' => $policy->recipientMode,
        ]));
    }

    private function update(int $uid, array $fields): int
    {
        $this->repository->update($uid, $fields);

        return $uid;
    }

    /**
     * Groups the several mails of one submission without identifying anybody.
     * Deliberately not derived from FormSession, which is null whenever the form
     * cannot process a session.
     */
    private function submissionId(): string
    {
        return $this->submissionId ??= bin2hex(random_bytes(16));
    }

    /**
     * @param Address[] $addresses
     * @return list<Address>
     */
    private function addresses(array $addresses): array
    {
        return array_values($addresses);
    }

    private function transportName(): string
    {
        try {
            $transport = $this->mailer->getRealTransport();
        } catch (\Throwable) {
            return '';
        }
        $name = $transport::class;
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    private function messageId(): string
    {
        try {
            return $this->mailer->getSentMessage()?->getMessageId() ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function featureEnabled(string $key, bool $default = false): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', $key);
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return $default;
        }
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    /**
     * Runs a write and swallows what it must.
     *
     * A missing table means the schema has not been applied yet after an update.
     * That is a deployment state, not a form problem, so the recorder steps
     * aside for the rest of the request rather than turning every submission
     * into a 500. Any other database error is logged and equally swallowed —
     * losing a log row is always preferable to losing an inquiry.
     *
     * @param callable(): int $write
     */
    private function guard(callable $write): ?int
    {
        try {
            return $write();
        } catch (TableNotFoundException $e) {
            $this->disabled = true;
            $this->logger?->warning(
                'Mail log table is missing, disabling the recorder for this request. Run the database schema update.',
                ['exception' => $e]
            );
        } catch (\Throwable $e) {
            $this->logger?->warning('Could not write the mail log row', ['exception' => $e]);
        }

        return null;
    }
}
