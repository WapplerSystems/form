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
use TYPO3\CMS\Form\Event\BeforeFinisherExecutedEvent;
use TYPO3\CMS\Form\Service\ConsentLogRecorder;

/**
 * Records the consent checkboxes of a submission.
 *
 * Hooked on BeforeFinisherExecutedEvent, and not filtered to EmailFinisher the
 * way the mail log is: consent belongs to the submission, so a form that only
 * writes to the database owes the same demonstration as one that mails.
 *
 * The event fires once per finisher, so the recorder deduplicates on the
 * submission id. It is the earliest point that means "this submission is being
 * processed": every element has passed validation by now, and the values are
 * still on the FormRuntime. An event before validation would record consents
 * from submissions that were then rejected.
 *
 * @internal not part of public TYPO3 Core API
 */
final class RecordConsents
{
    public function __construct(
        private readonly ConsentLogRecorder $recorder,
    ) {}

    #[AsEventListener(identifier: 'wapplersystems-form/consent-log/record')]
    public function record(BeforeFinisherExecutedEvent $event): void
    {
        $this->recorder->record($event->finisherContext);
    }
}
