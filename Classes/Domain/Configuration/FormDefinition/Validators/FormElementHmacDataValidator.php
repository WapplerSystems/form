<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Form\Domain\Configuration\FormDefinition\Validators;

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * @internal
 */
class FormElementHmacDataValidator extends ElementBasedValidator
{
    /**
     * Checks if the form element property value matches to its hmac hash.
     *
     * @param mixed $value
     */
    public function __invoke(string $key, $value): void
    {
        $dto = $this->validationDto->withPropertyPath($key);

        // WapplerSystems fork fix (Honeypot save):
        // Non-creatable elements (no formEditor.group, e.g. Honeypot) run every
        // property through the hmac check. But the form editor injects properties
        // from `predefinedDefaults` (e.g. `defaultValue: ""`) into the model AFTER
        // FormDefinitionConversionService::addHmacData() ran — so those properties
        // carry no `_orig_*` hmac and the value-by-hmac check would reject the save
        // with "No hmac found for property ... #1528588037".
        //
        // Mirror the creatable validator: if the property is a known
        // predefinedDefault in the form editor setup and has no hmac, accept it
        // only when it equals that predefined default value. This is secure —
        // only the unmodified default (which the editor itself injected) passes;
        // any tampered value still fails validateFormElementPredefinedDefaultValue.
        if (
            $this->getConfigurationService()->isFormElementPropertyDefinedInPredefinedDefaultsInFormEditorSetup($dto)
            && !ArrayUtility::isValidPath($this->currentElement, $this->buildHmacDataPath($dto->getPropertyPath()), '.')
        ) {
            $this->validateFormElementPredefinedDefaultValue($value, $dto);
            return;
        }

        $this->validateFormElementPropertyValueByHmacData(
            $this->currentElement,
            $value,
            $this->sessionToken,
            $dto
        );
    }
}
