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

use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched at the start of FormRuntime::mapAndValidatePage(), before any
 * per-element validation runs. Aggregate-level counterpart to the existing
 * BeforeRenderableIsValidatedEvent (which fires per element).
 *
 * Listeners can use this for setup work before cross-field validators run,
 * e.g. caching computed values shared between several validators.
 */
final class BeforeFormIsValidatedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly FormRuntime $formRuntime,
        public readonly RequestInterface $request,
    ) {}
}