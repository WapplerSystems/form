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

use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched from FormRuntime::triggerAfterFormStateInitialized() right after
 * the form state has been initialized (and after the legacy
 * SC_OPTIONS['ext/form']['afterFormStateInitialized'] hook objects ran).
 *
 * This is the PSR-14 replacement for that legacy hook and the canonical point
 * to PREFILL form values from outside the form definition — e.g. from the
 * logged-in fe_user, GET/POST parameters or a session. Listeners write values
 * through the FormRuntime's ArrayAccess API:
 *
 *     $event->formRuntime['email'] = $user->getEmail();
 *
 * Fires on every request (initial render and each step), so listeners that
 * should only prefill the first display must guard accordingly
 * (e.g. check $event->formRuntime->getFormState()->isFormSubmitted()).
 */
final class AfterFormStateInitializedEvent
{
    public function __construct(
        public readonly FormRuntime $formRuntime,
    ) {}
}
