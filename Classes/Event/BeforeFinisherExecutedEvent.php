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

use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;

/**
 * Dispatched from AbstractFinisher::execute() right before executeInternal()
 * runs, after the isEnabled() gate has passed. Generic across all finishers —
 * fires for Email, Redirect, Confirmation, SaveToDatabase, FlashMessage,
 * DeleteUploads and Closure alike (plus any custom finisher extending
 * AbstractFinisher).
 *
 * Listeners can:
 *  - call $finisherContext->cancel() to abort this finisher and skip
 *    remaining finishers in the chain
 *  - mutate the FinisherVariableProvider to inject runtime values consumed
 *    by the finisher
 *  - log finisher invocations for auditing
 */
final class BeforeFinisherExecutedEvent
{
    public function __construct(
        public readonly FinisherInterface $finisher,
        public readonly FinisherContext $finisherContext,
    ) {}
}