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

use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;

/**
 * Dispatched from AbstractFinisher::execute() when executeInternal() threw a
 * FinisherException — the third branch of the
 * BeforeFinisherExecutedEvent / AfterFinisherExecutedEvent pair, which until now
 * left the failure path as the only one nothing could observe from outside.
 *
 * Why this exists at all: a finisher failure was previously visible only as a
 * PSR-3 log line. On a live site that meant a contact form's notification mail
 * could fail on every single submission for over a week without anything
 * noticing, because nobody reads a log that has no reader. Anything that wants
 * to record, alert on, or count finisher failures needs a seam here.
 *
 * Why not a mail-specific event inside EmailFinisher: the two failures worth
 * catching are thrown at different points. A missing `senderAddress` throws
 * during option validation, before any mail object exists, while a transport
 * error throws from send(). Only this catch block sees both, and it already
 * holds the exception — including getPrevious(), which carries the original
 * transport exception for the latter.
 *
 * Does NOT fire when:
 *  - the finisher is disabled (execute() returns before the Before event)
 *  - executeInternal() threw something that is not a FinisherException. Notably
 *    an RFC-non-compliant sender address, a Fluid error in the mail template
 *    (FluidEmail renders lazily, so that surfaces inside send()), or a hard
 *    abort such as OOM. Consumers must therefore treat "no terminal event" as a
 *    possible outcome in its own right rather than assuming success.
 *
 * Listeners must not throw. This event is dispatched while the request is
 * already handling an error, and a secondary failure here would replace a
 * diagnosable finisher error with an unrelated stack trace.
 */
final class FinisherFailedEvent
{
    public function __construct(
        public readonly FinisherInterface $finisher,
        public readonly FinisherContext $finisherContext,
        public readonly FinisherException $exception,
    ) {}
}
