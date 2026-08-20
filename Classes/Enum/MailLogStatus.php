<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Enum;

/**
 * How far a logged notification mail got.
 *
 * The row is opened before the finisher runs and advanced as it progresses, so
 * the furthest phase reached IS the diagnosis. That matters because the two
 * interesting failures produce no event at all:
 *
 *  - PENDING left behind  → died before the mail object existed. Either the
 *    sender address was not RFC-compliant (EmailFinisher constructs the Address
 *    after the option checks, and RfcComplianceException is not a
 *    FinisherException, so nothing catches it), or the process was killed
 *    (OOM while reading upload attachments into memory is the realistic one).
 *  - PREPARED left behind → died inside the transport. FluidEmail renders its
 *    body lazily, so a broken mail template throws from within send(), past the
 *    finisher's TransportExceptionInterface catch. Genuinely "unknown whether it
 *    went out", and the module must say exactly that rather than guess.
 *
 * FAILED, by contrast, is a *reported* failure: a FinisherException was caught
 * and its code says which kind (1327060320/1327060200/1327060210 = a required
 * option was missing, 1754047320 = the transport refused it).
 *
 * There is deliberately no ABORTED case. "Stuck" is derived at query time from
 * `status IN (PENDING, PREPARED) AND crdate < now - grace`, not written by a
 * sweep — a monitoring feature that only tells the truth once someone remembers
 * to set up a second scheduler task is a monitoring feature that lies.
 */
enum MailLogStatus: int
{
    /** Row opened; the finisher started but the mail is not built yet. */
    case PENDING = 0;

    /** The mail is fully built and about to be handed to the transport. */
    case PREPARED = 1;

    /**
     * The transport accepted it. NOT "delivered" — with a spool transport this
     * means "queued", and with the `null` transport it means "discarded". The
     * `transport` column exists so this status stays interpretable.
     */
    case SENT = 2;

    /** A FinisherException was caught; error_code/error_message say why. */
    case FAILED = 3;

    /**
     * Whether this status is an end state. PENDING and PREPARED are not: a row
     * sitting in one of them is either in flight right now or was abandoned.
     */
    public function isTerminal(): bool
    {
        return $this === self::SENT || $this === self::FAILED;
    }

    /**
     * Label key in Database.xlf. Kept out of the database on purpose — storing
     * the label alongside the value would duplicate the vocabulary in the data
     * and let the two drift apart.
     */
    public function label(): string
    {
        return 'LLL:EXT:form/Resources/Private/Language/Database.xlf:mailLog.status.' . strtolower($this->name);
    }

    /**
     * Bootstrap contextual suffix for the badge in the backend module.
     */
    public function severity(): string
    {
        return match ($this) {
            self::SENT => 'success',
            self::FAILED => 'danger',
            self::PENDING, self::PREPARED => 'warning',
        };
    }
}
