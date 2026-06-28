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

use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched at the end of FormRuntime::mapAndValidatePage(), after all
 * per-element processing rules have run. The carried Result object is
 * mutable: listeners can call $event->result->forProperty(...)->addError(...)
 * to attach cross-field validation errors at this point.
 *
 * Primary consumer: form-level (cross-field) validators that need access to
 * the whole submitted dataset. Also intended for validation-logging
 * listeners that record failure patterns for drop-off analytics.
 */
final class AfterFormIsValidatedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly FormRuntime $formRuntime,
        public readonly RequestInterface $request,
        public readonly Result $result,
    ) {}
}