<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\Validation\ChallengeValidator;
use TYPO3\CMS\Form\Validation\FormLevelValidators;

/**
 * Runs ChallengeValidator for forms that switched the challenge on through
 * `renderingOptions.challenge.enable` rather than by listing the validator.
 *
 * The rendering option exists because that is how a site protects *every* form
 * at once (set it on the prototype), which is the form_crshield behaviour this
 * feature is modelled on — and a `validators` list on the form root cannot be
 * defaulted prototype-wide the way a rendering option can. Since a rendering
 * option is not a validator, nothing would otherwise evaluate it: form-level
 * validators are dispatched by RunFormLevelValidators from the form's own
 * processing rule, and this switch never puts anything there.
 *
 * Forms that list the `Challenge` validator explicitly are left alone — that
 * spelling already runs through RunFormLevelValidators, and running it here as
 * well would report the same rejection twice.
 */
#[AsEventListener('wapplersystems-form/validate-form-challenge')]
final class ValidateFormChallenge
{
    /**
     * Keys of `renderingOptions.challenge` that are ChallengeValidator options
     * rather than markup settings. Kept in sync with that class's
     * $supportedOptions — AbstractValidator throws on anything else.
     */
    private const VALIDATOR_OPTIONS = ['maxAge', 'errorMessage'];

    public function __construct(
        private readonly ValidatorResolver $validatorResolver,
    ) {}

    public function __invoke(AfterFormIsValidatedEvent $event): void
    {
        $formDefinition = $event->formRuntime->getFormDefinition();

        $challengeOptions = $formDefinition->getRenderingOptions()['challenge'] ?? null;
        if (!is_array($challengeOptions) || ($challengeOptions['enable'] ?? false) !== true) {
            return;
        }
        if (FormLevelValidators::has($formDefinition, ChallengeValidator::class)) {
            return;
        }

        $validator = $this->validatorResolver->createValidator(
            ChallengeValidator::class,
            $this->collectValidatorOptions($formDefinition, $challengeOptions),
            $event->request,
        );
        if (!$validator instanceof ChallengeValidator) {
            return;
        }
        $validator->setFormRuntime($event->formRuntime);

        $event->result
            ->forProperty($formDefinition->getIdentifier())
            ->merge($validator->validate($event->formRuntime->getFormState()->getFormValues()));
    }

    /**
     * `renderingOptions.challenge` mixes settings for two different consumers:
     * `delay` and `obfuscationMethod` shape the markup and belong to
     * InjectFormChallenge, the rest are validator options. Passing the whole
     * array through would make AbstractValidator throw on the unsupported keys,
     * so only the validator's own options are forwarded.
     *
     * The prototype's `validatorsDefinition` entry sits underneath them, exactly
     * as it does for a listed validator, so a site can change the default
     * message or max age once for all forms instead of per form.
     *
     * @param array<string, mixed> $challengeOptions
     * @return array<string, mixed>
     */
    private function collectValidatorOptions(FormDefinition $formDefinition, array $challengeOptions): array
    {
        $definition = $formDefinition->getValidatorsDefinition()['Challenge']['options'] ?? null;
        $options = is_array($definition) ? $definition : [];

        foreach (self::VALIDATOR_OPTIONS as $optionName) {
            if (array_key_exists($optionName, $challengeOptions)) {
                $options[$optionName] = $challengeOptions[$optionName];
            }
        }

        return array_intersect_key($options, array_flip(self::VALIDATOR_OPTIONS));
    }
}
