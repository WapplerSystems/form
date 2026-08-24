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
 * Rejects submissions that did not solve the JavaScript challenge.
 *
 * The counterpart of InjectFormChallenge (rendering) and challenge.js (client).
 * All this validator does is take the token the client wrote into the hidden
 * response field and hand it to FormChallengeService for signature, form-binding
 * and age verification — see that class for why the scheme is stateless and what
 * it does and does not prove.
 *
 * A client that never ran the JavaScript submits an empty response and is
 * rejected; a client that copied the challenge string verbatim submits a value
 * whose signature does not verify, because the obfuscation mangled it.
 *
 * Everything the shield needs is configured here, on the validator, rather than
 * split between a validator and a `renderingOptions.challenge` block: `delay`
 * and `obfuscationMethod` shape the markup, `maxAge` and `errorMessage` shape the
 * verdict, and having them in one place is what makes the feature
 * comprehensible. InjectFormChallenge reads the rendering-side options straight
 * off this validator when it finds it on the form.
 *
 * Putting the validator on the form is therefore the only switch:
 *
 *   type: Form
 *   identifier: contact
 *   validators:
 *     - identifier: Challenge
 *       options:
 *         delay: 3
 *         obfuscationMethod: rot13reverse
 *
 * The error is attached to the form root, not to a field: there is no field to
 * blame, and a spam shield reporting *which* mechanism caught a bot at the exact
 * field level would only help whoever is tuning their bot against it. The fork's
 * Form.html renders form-root errors as a summary above the form, so a
 * human who hits this (JavaScript disabled) still sees the message; templates
 * without such a summary reject silently, which for a shield is acceptable.
 */
final class ChallengeValidator extends AbstractFormAwareValidator
{
    /**
     * @var array<string, array{0: mixed, 1: string, 2?: string}>
     */
    protected $supportedOptions = [
        'delay' => [
            3,
            'Seconds the browser waits before answering the challenge. A submission sent earlier is rejected, so keep it well below the time a visitor needs. Read by InjectFormChallenge, not by this validator.',
            'integer',
        ],
        'obfuscationMethod' => [
            FormChallengeService::DEFAULT_OBFUSCATION_METHOD,
            'How the challenge is disguised in the markup: rot13reverse | rot13 | reverse | base64 | none. Any of them is reversed by the browser and the algorithm is public either way, so this only varies what a bot has to implement. Read by InjectFormChallenge, not by this validator.',
            'string',
        ],
        'maxAge' => [
            0,
            'Maximum age of the challenge in seconds; 0 disables the age check. Keep 0 unless the page holding the form is uncached — a max age below the page cache lifetime rejects legitimate submissions.',
            'integer',
        ],
        'errorMessage' => [
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.Challenge.errorMessage',
            'Error message shown when a challenge answer was submitted but does not verify. Accepts an LLL: reference.',
            'string',
        ],
        'errorMessageScriptMissing' => [
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.Challenge.errorMessageScriptMissing',
            'Error message shown when the browser never answered at all - the response field came back holding the sentinel it was rendered with, or empty. Separated from "errorMessage" because this case has something the visitor can act on: a blocked or failed script, rather than a wrong answer. Accepts an LLL: reference.',
            'string',
        ],
    ];

    /**
     * @param mixed $value The submitted form values; unused — the challenge
     *                     response is not a form element and therefore not part
     *                     of the mapped values, it is read from the request.
     */
    protected function isValid(mixed $value): void
    {
        $challengeService = GeneralUtility::makeInstance(FormChallengeService::class);

        $parsedBody = $this->formRuntime->getRequest()->getParsedBody();
        $response = is_array($parsedBody) ? ($parsedBody[FormChallengeService::RESPONSE_FIELD] ?? null) : null;

        // The sentinel is what the field is rendered with, so getting it back
        // means the script never ran. Telling the visitor that, instead of the
        // generic "could not be verified", is the difference between a message
        // they can act on and one they cannot.
        if (!is_string($response) || $response === '' || $response === FormChallengeService::SCRIPT_MISSING_SENTINEL) {
            $this->rejectScriptMissing();
            return;
        }

        $issuedAt = $challengeService->verifyToken(
            $response,
            $this->formRuntime->getFormDefinition()->getIdentifier(),
            (int)$this->options['maxAge']
        );
        if ($issuedAt === null) {
            $this->reject();
        }
    }

    private function reject(): void
    {
        $this->addError(
            $this->resolveErrorMessage(),
            1755648001
        );
    }

    /**
     * Distinct error code on purpose: the validation log then separates "no
     * script ran" from "wrong answer", which are different problems with
     * different fixes - the first is usually a blocker on a real visitor, the
     * second is usually a bot.
     */
    private function rejectScriptMissing(): void
    {
        $this->addError(
            $this->resolveErrorMessage('errorMessageScriptMissing'),
            1755648003
        );
    }
}
