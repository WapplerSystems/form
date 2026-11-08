<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\ViewHelpers;

use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Whether the kill switch currently blocks a form.
 *
 * Exists as a ViewHelper rather than as a controller assignment on purpose:
 * the one place that needs it in the backend is the form manager list, whose
 * controller is an upstream file. Adding a constructor argument there would
 * break every upstream cherry-pick and every downstream subclass for the sake
 * of one badge — and it did break two functional tests that build the
 * controller by hand. A ViewHelper gets its dependency injected without
 * touching any of that, and a site package can use the same helper in a
 * frontend template.
 *
 * Fluid usage:
 *   {namespace formvh=TYPO3\CMS\Form\ViewHelpers}
 *   <f:if condition="{formvh:formIsBlocked(identifier: form.identifier)}">…</f:if>
 *
 * Omitting `identifier` answers whether EVERYTHING is blocked.
 */
final class FormIsBlockedViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly FormKillSwitch $killSwitch,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'identifier',
            'string',
            'Form identifier. Empty asks whether every form is blocked.',
            false,
            ''
        );
        $this->registerArgument(
            'site',
            'string',
            'Site identifier, to take a site-wide block into account as well.',
            false,
            ''
        );
    }

    public function render(): bool
    {
        return $this->killSwitch->isDisabled(
            (string)$this->arguments['identifier'],
            (string)$this->arguments['site']
        );
    }
}
