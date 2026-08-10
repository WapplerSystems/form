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
 * Dispatched from AbstractFinisher::execute() right after executeInternal()
 * returns successfully. Does NOT fire when:
 *  - the finisher is disabled (isEnabled() returned false)
 *  - executeInternal() threw a FinisherException (the catch branch handles
 *    that path separately)
 *
 * Listeners receive the value executeInternal() produced (string|null) and
 * can log, transform output, or trigger follow-up actions.
 */
final class AfterFinisherExecutedEvent
{
    public function __construct(
        public readonly FinisherInterface $finisher,
        public readonly FinisherContext $finisherContext,
        public readonly mixed $result,
    ) {}
}