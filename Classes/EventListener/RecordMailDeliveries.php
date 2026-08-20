<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Event\AfterMailSentEvent;
use TYPO3\CMS\Form\Event\BeforeFinisherExecutedEvent;
use TYPO3\CMS\Form\Event\FinisherFailedEvent;
use TYPO3\CMS\Form\Event\MailBeforeSendingEvent;
use TYPO3\CMS\Form\Service\MailLogRecorder;

/**
 * Wires the four points of a notification mail's life into the mail log.
 *
 * Deliberately thin: every decision lives in MailLogRecorder and MailLogPolicy,
 * which are plain injectable classes and therefore unit-testable without a
 * request, a database or an event dispatcher. All this class contributes is the
 * mapping from events to recorder calls, plus the `instanceof` filter that keeps
 * a mail log a mail log.
 *
 * The entry point is BeforeFinisherExecutedEvent rather than the mail-specific
 * MailBeforeSendingEvent, which looks like the obvious choice and is not: a
 * missing `senderAddress` throws during option validation, before any mail
 * object exists and therefore before any mail-specific event is dispatched.
 * Starting there would leave a form whose notification mail has been failing on
 * every submission with no log row at all — which is precisely the failure that
 * went unnoticed for over a week and prompted this feature.
 *
 * @internal not part of public TYPO3 Core API
 */
final class RecordMailDeliveries
{
    public function __construct(
        private readonly MailLogRecorder $recorder,
    ) {}

    #[AsEventListener(identifier: 'wapplersystems-form/mail-log/open')]
    public function open(BeforeFinisherExecutedEvent $event): void
    {
        if (!$event->finisher instanceof EmailFinisher) {
            return;
        }

        $this->recorder->open($event->finisher, $event->finisherContext);
    }

    #[AsEventListener(identifier: 'wapplersystems-form/mail-log/prepare')]
    public function prepare(MailBeforeSendingEvent $event): void
    {
        $this->recorder->prepare($event->mail, $event->finisher);
    }

    #[AsEventListener(identifier: 'wapplersystems-form/mail-log/sent')]
    public function sent(AfterMailSentEvent $event): void
    {
        $this->recorder->sent($event->finisher);
    }

    #[AsEventListener(identifier: 'wapplersystems-form/mail-log/failed')]
    public function failed(FinisherFailedEvent $event): void
    {
        if (!$event->finisher instanceof EmailFinisher) {
            return;
        }

        $this->recorder->failed($event->finisher, $event->finisherContext, $event->exception);
    }
}
