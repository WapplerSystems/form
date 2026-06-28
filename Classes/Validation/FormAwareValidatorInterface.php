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

use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Validators implementing this interface are invoked at form aggregate level
 * (not per element). Before validate() is called the dispatcher injects the
 * FormRuntime so the validator can see ALL submitted values and the full
 * form structure — this is the contract that unblocks cross-field validation
 * (entropy spam filter, "X required if Y set", sums, totals, etc).
 *
 * Form-level validators are declared under
 *   renderingOptions.formLevelValidators
 * on the form root in the form YAML. The implementationClassName must be
 * registered in the prototype's validatorsDefinition just like any other
 * validator.
 */
interface FormAwareValidatorInterface extends ValidatorInterface
{
    public function setFormRuntime(FormRuntime $formRuntime): void;
}
