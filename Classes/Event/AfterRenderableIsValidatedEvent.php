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
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Per-renderable counterpart to the upstream BeforeRenderableIsValidatedEvent:
 * dispatched from FormRuntime::mapAndValidatePage() right after a single
 * renderable's processing rule (validators + type conversion) has run and its
 * messages were merged into the validation result.
 *
 * Fires only for renderables that actually have a processing rule (i.e. carry
 * validators / a type converter). `$validationResult` is the field-scoped
 * sub-result — listeners may inspect it or add/override field errors via
 * `$event->validationResult->addError(...)`. For the aggregate result of the
 * whole page use AfterFormIsValidatedEvent instead.
 */
final class AfterRenderableIsValidatedEvent
{
    public function __construct(
        public readonly RenderableInterface $renderable,
        public mixed $value,
        public readonly FormRuntime $formRuntime,
        public readonly RequestInterface $request,
        public readonly Result $validationResult,
    ) {}
}
