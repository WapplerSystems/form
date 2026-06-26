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
 * Dispatched once from FormRuntime::invokeFinishers() after the complete
 * finisher chain has run (or was cancelled), right before the rendered output
 * is returned.
 *
 * Whereas Before/AfterFinisherExecutedEvent fire per finisher, this event
 * fires exactly once per successful submission — the natural hook for
 * "submission complete" actions: conversion / analytics tracking, CRM sync,
 * a single follow-up action that must not run per finisher.
 *
 * Carries the final form values, the assembled finisher output and whether the
 * chain was cancelled (a finisher called FinisherContext::cancel()).
 */
final class AfterFormSubmittedEvent
{
    /**
     * @param array<string, mixed> $formValues
     */
    public function __construct(
        public readonly FormRuntime $formRuntime,
        public readonly array $formValues,
        public readonly string $renderedOutput,
        public readonly bool $wasCancelled,
    ) {}
}
