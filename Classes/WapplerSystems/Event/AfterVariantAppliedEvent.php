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

use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableVariantInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\VariableRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched in FormRuntime::processVariants() each time a variant's
 * condition matched and its property overrides have been applied to a
 * renderable. Fires once per applied variant per processVariants() pass
 * (which itself runs three times in the form lifecycle: after form state
 * init, before render, and before finisher execution).
 *
 * Listeners can use this to react to dynamic form structure changes
 * (e.g. invalidate caches, log condition matches for analytics).
 */
final class AfterVariantAppliedEvent
{
    public function __construct(
        public readonly VariableRenderableInterface $renderable,
        public readonly RenderableVariantInterface $variant,
        public readonly FormRuntime $formRuntime,
    ) {}
}