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

use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched from FormRuntime::render() right after the renderer produced the
 * form markup (the page-rendering path only — not the finisher-output path,
 * which is covered by AfterFormSubmittedEvent).
 *
 * `$renderedContent` is MUTABLE: listeners may rewrite or wrap the markup —
 * inject a tracking pixel, a JSON island for client-side logic (e.g. the
 * frontend live-conditions feature), CSP nonces, etc. FormRuntime returns the
 * (possibly modified) `$renderedContent`.
 */
final class AfterFormRenderedEvent
{
    public function __construct(
        public readonly FormRuntime $formRuntime,
        public string $renderedContent,
    ) {}
}
