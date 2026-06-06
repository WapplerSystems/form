<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Validation;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Convenience base class for form-aware (cross-field) validators. Implements
 * the interface and exposes a getSubmittedValues() helper so concrete
 * validators don't have to walk through FormRuntime->getFormState() each time.
 */
abstract class AbstractFormAwareValidator extends AbstractValidator implements FormAwareValidatorInterface
{
    protected FormRuntime $formRuntime;

    public function setFormRuntime(FormRuntime $formRuntime): void
    {
        $this->formRuntime = $formRuntime;
    }

    /**
     * @return array<string, mixed> All submitted form values keyed by element identifier.
     */
    protected function getSubmittedValues(): array
    {
        return $this->formRuntime->getFormState()->getFormValues();
    }
}
