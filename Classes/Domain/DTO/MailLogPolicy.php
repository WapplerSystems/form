<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\DTO;

use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

/**
 * What the mail log is allowed to record for one particular mail.
 *
 * The central decision here is that the *rows* and the *personal-data columns*
 * are gated separately.
 *
 * A row carrying only form identifier, finisher, status, error code and
 * timestamps contains no personal data whatsoever, so it needs no opt-in to be
 * lawful. Recipient, subject, sender and reply-to do, so those stay opt-in per
 * form. Gating the whole row instead would reproduce the failure this feature
 * exists to prevent: the form nobody is watching is precisely the form nobody
 * opts in, and it stayed broken for over a week for exactly that reason.
 *
 * Resolution order, narrowest wins:
 *   extension configuration  →  form renderingOptions.mailLog  →  finisher options.mailLog
 *
 * The per-finisher level is not decoration. "The recipient is our own inbox, so
 * there is no personal data" holds for EmailToReceiver and is false for
 * EmailToSender, where the recipient IS the visitor — a form-level setting
 * cannot express that difference.
 */
readonly class MailLogPolicy
{
    /**
     * Recipient formatting modes, narrowest last.
     */
    public const RECIPIENT_MODES = ['full', 'hashed', 'domain', 'none'];

    /**
     * `domain` rather than `full`, so that the *unconfigured* case is already
     * acceptable for a mail going to a visitor: "it went to a gmail address"
     * answers "did it go out?" without storing who.
     */
    public const DEFAULT_RECIPIENT_MODE = 'domain';

    /**
     * FinisherException code for a transport failure. Its message wraps the
     * SMTP response, which routinely quotes the recipient address
     * ("550 5.1.1 <john.doe@example.com>: user unknown") and is therefore the
     * one error text that can carry personal data.
     */
    private const TRANSPORT_FAILURE_CODE = 1754047320;

    private function __construct(
        public bool $enabled,
        public bool $personalData,
        public string $recipientMode,
        public bool $logSubject,
        public bool $logSender,
        public bool $logReplyTo,
        public bool $errorDetail,
    ) {}

    /**
     * @param array<string, mixed> $formRenderingOptions The form's renderingOptions
     * @param array<string, mixed> $finisherOptions      The finisher's own options
     */
    public static function resolve(
        array $formRenderingOptions,
        array $finisherOptions,
        bool $masterSwitch,
        bool $allForms,
    ): self {
        $formSettings = self::asArray($formRenderingOptions['mailLog'] ?? null);
        $finisherSettings = self::asArray($finisherOptions['mailLog'] ?? null);
        $settings = array_replace($formSettings, $finisherSettings);

        // null (or absent) means "inherit"; an explicit false switches this form
        // off even when the instance logs everything else.
        $optIn = $settings['enable'] ?? null;
        $optIn = $optIn === null ? null : (bool)$optIn;

        if (!$masterSwitch || $optIn === false) {
            return self::disabled();
        }

        $optedIn = $optIn === true;
        if (!$optedIn && !$allForms) {
            return self::disabled();
        }

        return new self(
            enabled: true,
            personalData: $optedIn,
            // Without an opt-in nothing identifying is stored, so the narrowest
            // mode is not a default here but a hard floor.
            recipientMode: $optedIn ? self::normalizeMode($settings['recipients'] ?? null) : 'none',
            logSubject: $optedIn && (bool)($settings['subject'] ?? false),
            logSender: $optedIn && (bool)($settings['sender'] ?? false),
            logReplyTo: $optedIn && (bool)($settings['replyTo'] ?? false),
            errorDetail: (bool)($settings['errorDetail'] ?? true),
        );
    }

    public static function disabled(): self
    {
        return new self(
            enabled: false,
            personalData: false,
            recipientMode: 'none',
            logSubject: false,
            logSender: false,
            logReplyTo: false,
            errorDetail: false,
        );
    }

    /**
     * Whether the text of a failure may be stored, judged per error kind rather
     * than by one blanket switch.
     *
     * A configuration failure reads "The option senderAddress must be set for
     * the EmailFinisher." — no personal data, and the single most useful string
     * this log can hold. A transport failure quotes the SMTP response and can
     * name the recipient, so it needs the same opt-in as the recipient column
     * itself.
     *
     * Consequence worth stating: a form that never opted in still records the
     * full text of its configuration errors. That is what let the broken
     * monitoring form diagnose itself without anyone having configured anything.
     */
    public function mayStoreErrorMessage(int $errorCode): bool
    {
        if (!$this->errorDetail) {
            return false;
        }

        return $errorCode !== self::TRANSPORT_FAILURE_CODE || $this->personalData;
    }

    /**
     * Whether the exception's own message may be stored, for an exception that
     * is not a FinisherException and therefore carries no fork-assigned code.
     */
    public function mayStoreExceptionMessage(\Throwable $exception): bool
    {
        $code = $exception instanceof FinisherException ? (int)$exception->getCode() : 0;

        return $this->mayStoreErrorMessage($code);
    }

    private static function normalizeMode(mixed $mode): string
    {
        return is_string($mode) && in_array($mode, self::RECIPIENT_MODES, true)
            ? $mode
            : self::DEFAULT_RECIPIENT_MODE;
    }

    /**
     * @return array<string, mixed>
     */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
