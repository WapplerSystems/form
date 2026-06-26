<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\CMS\Form\WapplerSystems\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\WapplerSystems\Validation\FormAwareValidatorInterface;

/**
 * Invokes form-level (cross-field) validators declared in the form YAML
 * under `renderingOptions.formLevelValidators`. Runs after per-element
 * validation has completed (via AfterFormIsValidatedEvent), so all
 * submitted values are already mapped into the form state when validators
 * see them.
 *
 * Each entry shape:
 *   renderingOptions:
 *     formLevelValidators:
 *       - identifier: EntropySpam
 *         options:
 *           minimumEntropy: 2.0
 *           textFieldIdentifiers: ['message', 'subject']
 *
 * The validator must be registered in the prototype's validatorsDefinition.
 * If a validator additionally implements FormAwareValidatorInterface, the
 * FormRuntime is injected before validate() runs.
 *
 * Errors produced by form-level validators are merged into the form's
 * aggregate Result under the form-root propertyPath; validators that want
 * to attach errors to a specific element can do so themselves via
 * $this->result->forProperty(...)->addError(...) inside isValid().
 *
 * Priority `before: ...` is not used — running before other listeners on
 * AfterFormIsValidatedEvent is desirable but not required for correctness
 * (the Result is mutable; later listeners observe the merged state).
 */
#[AsEventListener('wapplersystems-form/run-form-level-validators')]
final class RunFormLevelValidators
{
    public function __construct(
        private readonly ValidatorResolver $validatorResolver,
    ) {}

    public function __invoke(AfterFormIsValidatedEvent $event): void
    {
        $formDefinition = $event->formRuntime->getFormDefinition();
        $renderingOptions = $formDefinition->getRenderingOptions();
        $formLevelValidators = $renderingOptions['formLevelValidators'] ?? null;
        if (!is_array($formLevelValidators) || $formLevelValidators === []) {
            return;
        }

        $validatorsDefinition = $formDefinition->getValidatorsDefinition();
        $submittedValues = $event->formRuntime->getFormState()->getFormValues();
        $formIdentifier = $formDefinition->getIdentifier();

        foreach ($formLevelValidators as $validatorConfig) {
            if (!is_array($validatorConfig) || !isset($validatorConfig['identifier'])) {
                continue;
            }
            $identifier = (string)$validatorConfig['identifier'];
            $definition = $validatorsDefinition[$identifier] ?? null;
            if (!is_array($definition) || !isset($definition['implementationClassName'])) {
                continue;
            }

            $defaultOptions = is_array($definition['options'] ?? null) ? $definition['options'] : [];
            $userOptions = is_array($validatorConfig['options'] ?? null) ? $validatorConfig['options'] : [];
            $options = array_replace_recursive($defaultOptions, $userOptions);

            $validator = $this->validatorResolver->createValidator(
                (string)$definition['implementationClassName'],
                $options,
                $event->request,
            );
            if ($validator === null) {
                continue;
            }
            if ($validator instanceof FormAwareValidatorInterface) {
                $validator->setFormRuntime($event->formRuntime);
            }

            $validationResult = $validator->validate($submittedValues);
            $event->result->forProperty($formIdentifier)->merge($validationResult);
        }
    }
}
