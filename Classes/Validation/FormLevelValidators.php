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

use TYPO3\CMS\Form\Domain\Model\FormDefinition;

/**
 * Answers "does this form carry a form-level validator of class X?" for callers
 * outside the validation run itself — the rendering side, which has to decide
 * whether a validator needs client-side support emitted into the markup.
 *
 * The fork accepts two spellings for form-level validators (see
 * RunFormLevelValidators) and they end up in different places: the conventional
 * top-level `validators` list is instantiated onto the form's own processing
 * rule, while the older `renderingOptions.formLevelValidators` stays raw
 * configuration until the dispatcher resolves it. Callers should not have to
 * know that, hence this lookup.
 */
final class FormLevelValidators
{
    /**
     * The effective options of a form-level validator, or NULL when the form does
     * not carry one of that class.
     *
     * Lets the rendering side read a validator's own configuration instead of
     * duplicating it into a parallel `renderingOptions` block — which is how the
     * challenge ended up with its settings in two places.
     *
     * @param class-string $validatorClassName
     * @param non-empty-string $validatorIdentifier Key under validatorsDefinition, for the legacy spelling
     * @return array<string, mixed>|null
     */
    public static function findOptions(
        FormDefinition $formDefinition,
        string $validatorClassName,
        string $validatorIdentifier,
    ): ?array {
        // The conventional `validators:` list is already instantiated onto the
        // form's own processing rule, so the validator itself is authoritative:
        // setOptions() has merged the prototype defaults under the form's values.
        foreach ($formDefinition->getProcessingRule($formDefinition->getIdentifier())->getValidators() as $validator) {
            if ($validator instanceof $validatorClassName) {
                return $validator->getOptions();
            }
        }

        // The older renderingOptions.formLevelValidators spelling is still raw
        // configuration at this point, so merge it the same way the dispatcher
        // will later.
        $formLevelValidators = $formDefinition->getRenderingOptions()['formLevelValidators'] ?? null;
        if (!is_array($formLevelValidators)) {
            return null;
        }
        $definition = $formDefinition->getValidatorsDefinition()[$validatorIdentifier] ?? null;
        $defaults = is_array($definition['options'] ?? null) ? $definition['options'] : [];

        foreach ($formLevelValidators as $validatorConfiguration) {
            if (!is_array($validatorConfiguration)
                || (string)($validatorConfiguration['identifier'] ?? '') !== $validatorIdentifier
            ) {
                continue;
            }
            $options = is_array($validatorConfiguration['options'] ?? null) ? $validatorConfiguration['options'] : [];

            return array_replace($defaults, $options);
        }

        return null;
    }

    /**
     * @param class-string $validatorClassName
     */
    public static function has(FormDefinition $formDefinition, string $validatorClassName): bool
    {
        foreach ($formDefinition->getProcessingRule($formDefinition->getIdentifier())->getValidators() as $validator) {
            if ($validator instanceof $validatorClassName) {
                return true;
            }
        }

        $formLevelValidators = $formDefinition->getRenderingOptions()['formLevelValidators'] ?? null;
        if (!is_array($formLevelValidators)) {
            return false;
        }

        $validatorsDefinition = $formDefinition->getValidatorsDefinition();
        foreach ($formLevelValidators as $validatorConfiguration) {
            if (!is_array($validatorConfiguration) || !isset($validatorConfiguration['identifier'])) {
                continue;
            }
            $implementationClassName = $validatorsDefinition[(string)$validatorConfiguration['identifier']]['implementationClassName'] ?? null;
            if (!is_string($implementationClassName)) {
                continue;
            }
            if ($implementationClassName === $validatorClassName || is_subclass_of($implementationClassName, $validatorClassName)) {
                return true;
            }
        }

        return false;
    }
}
