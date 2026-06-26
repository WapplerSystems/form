<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Event;

use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched once from FormRuntime::invokeFinishers() right before the finisher
 * chain starts — the counterpart to AfterFormSubmittedEvent.
 *
 * The `$finishers` list is MUTABLE: listeners may reorder it, remove entries or
 * inject additional finishers; FormRuntime iterates the (possibly modified)
 * list afterwards. Listeners may also call `$finisherContext->cancel()` to skip
 * the whole chain, or use the shared FinisherVariableProvider on the context to
 * inject values consumed by later finishers.
 */
final class BeforeFinishersInvokedEvent
{
    /**
     * @var FinisherInterface[]
     */
    public array $finishers;

    /**
     * @param FinisherInterface[] $finishers
     */
    public function __construct(
        public readonly FormRuntime $formRuntime,
        array $finishers,
        public readonly FinisherContext $finisherContext,
    ) {
        $this->finishers = $finishers;
    }
}
