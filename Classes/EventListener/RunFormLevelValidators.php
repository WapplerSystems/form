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
use TYPO3\CMS\Form\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\Validation\FormAwareValidatorInterface;

/**
 * Invokes form-level (cross-field) validators. Runs after per-element
 * validation has completed (via AfterFormIsValidatedEvent), so all
 * submitted values are already mapped into the form state when validators
 * see them.
 *
 * Declare them the same way as on any other renderable — a `validators:` list,
 * here on the form itself:
 *
 *   identifier: ContactForm
 *   type: Form
 *   validators:
 *     - identifier: EntropySpam
 *       options:
 *         minimumEntropy: 2.0
 *         textFieldIdentifiers: ['message', 'subject']
 *
 * AbstractRenderable::setOptions() already accepts `validators` on the form and
 * registers them on the root form's processing rule under the form identifier —
 * but nothing in core ever evaluates that rule, because processing rules are
 * applied per element during property mapping. So the conventional spelling
 * silently did nothing. This listener gives it meaning.
 *
 * `renderingOptions.formLevelValidators` remains supported for definitions
 * written against the earlier fork behaviour; entries from both sources run.
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
        $formLevelValidators = is_array($formLevelValidators) ? $formLevelValidators : [];

        $validatorsDefinition = $formDefinition->getValidatorsDefinition();
        $submittedValues = $event->formRuntime->getFormState()->getFormValues();
        $formIdentifier = $formDefinition->getIdentifier();

        // Validators declared conventionally as `validators:` on the form landed
        // in the root form's processing rule keyed by the form identifier. They
        // are already instantiated, so run them directly instead of resolving
        // them again from the definition.
        foreach ($formDefinition->getProcessingRule($formIdentifier)->getValidators() as $validator) {
            if ($validator instanceof FormAwareValidatorInterface) {
                $validator->setFormRuntime($event->formRuntime);
            }
            $event->result->forProperty($formIdentifier)->merge($validator->validate($submittedValues));
        }

        if ($formLevelValidators === []) {
            return;
        }

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
