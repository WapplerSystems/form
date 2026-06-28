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
 * Dispatched from EmailFinisher::executeInternal() immediately after
 * MailerInterface::send() returned successfully.
 *
 * Complements MailBeforeSendingEvent (which fires before transport and cannot
 * distinguish success from failure): this event fires ONLY on a successful
 * send and never when the transport throws — making it the reliable hook for
 * "delivered" audit logging and post-delivery follow-up actions.
 */
final class AfterMailSentEvent
{
    public function __construct(
        public readonly FluidEmail $mail,
        public readonly FinisherContext $finisherContext,
        public readonly EmailFinisher $finisher,
    ) {}
}
