<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Validation;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Security\FormChallengeService;

/**
 * Rejects submissions that arrived faster than a human could plausibly have
 * filled the form in.
 *
 * A form-level validator: put it on the form root (not on an element), which is
 * also what makes InjectFormChallenge emit the hidden field and the frontend
 * module that measure the time — the validator's presence is the switch, there
 * is no second one.
 *
 *   identifier: ContactForm
 *   type: Form
 *   validators:
 *     - identifier: MinimumFillTime
 *       options:
 *         minimumSeconds: 5
 *
 * Two independent checks
 * ----------------------
 * 1. The elapsed time the frontend module measured and submitted. This is the
 *    check that catches the common case, and it is client-asserted: a bot that
 *    runs JavaScript and rewrites the field can claim any duration. It is not
 *    meant to be unforgeable — it is meant to cost more than the average
 *    spam run is willing to pay, and it is the only measurement available at
 *    all under full page caching (see below).
 *
 * 2. The age of the challenge token, when the challenge is switched on as well.
 *    That timestamp comes from the server, so this check cannot be forged — but
 *    it only says how long ago the *markup* was produced, which for a cached
 *    page is not when the visitor started filling anything in. So it can only
 *    ever prove a submission is too fast, never that it is fast enough. It costs
 *    nothing, never produces a false positive (the form cannot have been on
 *    screen longer than it has existed), and it catches a bot that fakes the
 *    elapsed time but submits immediately.
 *
 * Why the measurement is client-side at all
 * -----------------------------------------
 * The initial render goes through the cacheable `render` action, so any
 * timestamp the server writes into the markup is the cache-fill time, shared by
 * every visitor for the whole cache lifetime — useless as a per-visitor start
 * time. Measuring in the browser is what makes the check work on cached pages.
 *
 * Multi-step forms
 * ----------------
 * Every step render restarts the timer, and per-page validation runs on every
 * step, so the configured minimum applies *per displayed step* rather than to
 * the whole form. That is the more useful reading of the setting — a bot has to
 * pay the delay once per step — but it does mean `minimumSeconds` should be
 * sized for a single step, not for the whole form.
 */
final class MinimumFillTimeValidator extends AbstractFormAwareValidator
{
    /**
     * @var array<string, array{0: mixed, 1: string, 2?: string}>
     */
    protected $supportedOptions = [
        'minimumSeconds' => [
            5,
            'Minimum number of seconds a visitor must spend on the displayed form step before the submission is accepted.',
            'integer',
        ],
        'allowMissingTimingData' => [
            false,
            'Accept submissions that carry no measurement at all (JavaScript disabled or stripped). Off by default: a client that reports nothing is exactly what a bot does, and letting it through would make the check trivially bypassable. Turn it on for a form that must stay usable without JavaScript — the check then only rejects submissions that are demonstrably too fast.',
            'boolean',
        ],
        'errorMessage' => [
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.MinimumFillTime.errorMessage',
            'Error message shown when the submission was too fast. Accepts an LLL: reference.',
            'string',
        ],
    ];

    /**
     * @param mixed $value The submitted form values; unused — the measurement is
     *                     not a form element and therefore not part of the
     *                     mapped values, it is read from the request.
     */
    protected function isValid(mixed $value): void
    {
        $minimumSeconds = (int)$this->options['minimumSeconds'];
        if ($minimumSeconds <= 0) {
            return;
        }

        $parsedBody = $this->formRuntime->getRequest()->getParsedBody();
        $parsedBody = is_array($parsedBody) ? $parsedBody : [];

        $elapsedSeconds = $this->getMeasuredSeconds($parsedBody);
        if ($elapsedSeconds === null) {
            if (!(bool)$this->options['allowMissingTimingData']) {
                $this->reject();
            }
            return;
        }

        if ($elapsedSeconds < $minimumSeconds) {
            $this->reject();
            return;
        }

        // Server-side corroboration: a challenge token, when present, carries the
        // render time. Cheap, unforgeable, and one-sided — see the class docblock.
        $tokenAgeSeconds = $this->getChallengeTokenAge($parsedBody);
        if ($tokenAgeSeconds !== null && $tokenAgeSeconds < $minimumSeconds) {
            $this->reject();
        }
    }

    /**
     * The measured time in seconds, or NULL when the field is absent or does not
     * hold a usable number.
     *
     * @param array<mixed> $parsedBody
     */
    private function getMeasuredSeconds(array $parsedBody): ?float
    {
        $milliseconds = $parsedBody[FormChallengeService::FILL_TIME_FIELD] ?? null;
        if (!is_string($milliseconds) && !is_int($milliseconds)) {
            return null;
        }
        $milliseconds = (string)$milliseconds;
        // Deliberately strict: a bot writing "9999s" or "" should count as "no
        // measurement" and be handled by allowMissingTimingData, not silently
        // pass through a lenient cast.
        if ($milliseconds === '' || !ctype_digit($milliseconds)) {
            return null;
        }
        return ((float)$milliseconds) / 1000;
    }

    /**
     * Age in seconds of the challenge token that came with the submission, or
     * NULL when there is none or it does not verify. An unverifiable token is
     * not this validator's problem to report — ChallengeValidator handles that,
     * and if the challenge is switched off there is nothing to report at all.
     *
     * @param array<mixed> $parsedBody
     */
    private function getChallengeTokenAge(array $parsedBody): ?int
    {
        $token = $parsedBody[FormChallengeService::RESPONSE_FIELD] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        $issuedAt = GeneralUtility::makeInstance(FormChallengeService::class)->verifyToken(
            $token,
            $this->formRuntime->getFormDefinition()->getIdentifier()
        );
        if ($issuedAt === null) {
            return null;
        }

        return max(0, time() - $issuedAt);
    }

    private function reject(): void
    {
        $this->addError(
            $this->resolveErrorMessage(),
            1755648002
        );
    }
}
