<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Event;

use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;

/**
 * Dispatched from EmailFinisher::executeInternal() immediately before
 * MailerInterface::send() is called. Listeners can mutate the FluidEmail
 * in-place — add/remove recipients, override headers, attach extra files,
 * change subject, etc. The FluidEmail reference is readonly but the object
 * itself is fully mutable through its standard API.
 *
 * Complements the upstream BeforeEmailFinisherInitializedEvent which fires
 * at the start of executeInternal() with raw options; this event fires
 * after the FluidEmail is fully constructed.
 *
 * Promoted from wapplersystems/form_extended into the fork as part of
 * Phase 2 of the form_extended migration.
 */
final class MailBeforeSendingEvent
{
    public function __construct(
        public readonly FluidEmail $mail,
        public readonly FinisherContext $finisherContext,
        public readonly EmailFinisher $finisher,
    ) {}
}
