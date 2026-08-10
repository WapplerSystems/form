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

use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched at the start of FormRuntime::processSubmittedFormValues(), before
 * the submitted page is mapped and validated. Listeners can use this to
 * preprocess submitted request arguments, snapshot form state for analytics,
 * or short-circuit submission flows via FormRuntime state.
 *
 * The page carried here is the lastDisplayedPage (the page whose submission
 * is currently being processed), not necessarily the form's currentPage.
 */
final class BeforeFormPageProcessedEvent
{
    public function __construct(
        public readonly Page $page,
        public readonly FormRuntime $formRuntime,
        public readonly RequestInterface $request,
    ) {}
}