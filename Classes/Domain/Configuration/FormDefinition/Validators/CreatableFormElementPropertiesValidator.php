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
use TYPO3\CMS\Form\Domain\Configuration\Exception\PropertyException;

/**
 * @internal
 */
class CreatableFormElementPropertiesValidator extends ElementBasedValidator
{
    /**
     * Checks if the form element property is defined within the form editor setup
     * or if the property is defined within the "predefinedDefaults" in the form editor setup
     * and the property value matches the predefined value
     * or if there is a valid hmac hash for the value.
     * If the form element property is defined within the form editor setup
     * and there is no valid hmac hash for the value
     * and is the form element property configured to only allow a limited set of values,
     * check the current (submitted) value against the allowed set of values (defined within the form setup).
     *
     * @param mixed $value
     */
    public function __invoke(string $key, $value)
    {
        $dto = $this->validationDto->withPropertyPath($key);

        if ($this->getConfigurationService()->isFormElementPropertyDefinedInFormEditorSetup($dto)) {
            if ($this->getConfigurationService()->formElementPropertyHasLimitedAllowedValuesDefinedWithinFormEditorSetup($dto)) {
                $this->validateFormElementValue($value, $dto);
            }
        } elseif (
            $this->getConfigurationService()->isFormElementPropertyDefinedInPredefinedDefaultsInFormEditorSetup($dto)
            && !ArrayUtility::isValidPath($this->currentElement, $this->buildHmacDataPath($dto->getPropertyPath()), '.')
        ) {
            $this->validateFormElementPredefinedDefaultValue($value, $dto);
        } else {
            $this->validateFormElementPropertyValueByHmacData(
                $this->currentElement,
                $value,
                $this->sessionToken,
                $dto
            );
        }
    }

    // validateFormElementPredefinedDefaultValue() lives in the shared base
    // ElementBasedValidator (WapplerSystems fork) so the non-creatable
    // FormElementHmacDataValidator can reuse the same predefined-default check.

    /**
     * Throws an exception if the value from a form element property
     * does not match the allowed set of values (defined within the form setup).
     *
     * @param mixed $value
     * @throws PropertyException
     */
    protected function validateFormElementValue(
        $value,
        ValidationDto $dto
    ): void {
        $allowedValues = $this->getConfigurationService()->getAllowedValuesForFormElementPropertyFromFormEditorSetup($dto);

        if (!in_array($value, $allowedValues, true)) {
            $untranslatedAllowedValues = $this->getConfigurationService()->getAllowedValuesForFormElementPropertyFromFormEditorSetup($dto, false);
            // Compare the $value against the untranslated set of allowed values
            if (in_array($value, $untranslatedAllowedValues, true)) {
                // All good, $value is within the untranslated set of allowed values
                return;
            }
            // Get all translations (from all backend languages) for the untranslated! $allowedValues and
            // compare the (already translated) $value (from the form definition) against all possible
            // translations for $untranslatedAllowedValues.
            $allPossibleAllowedValuesTranslations = $this->getConfigurationService()->getAllBackendTranslationsForTranslationKeys(
                $untranslatedAllowedValues,
                $dto->getPrototypeName()
            );

            foreach ($allPossibleAllowedValuesTranslations as $translations) {
                if (in_array($value, $translations, true)) {
                    // All good, $value is within the set of translated allowed values
                    return;
                }
            }

            // Last chance:
            // If $value is not configured within the form setup as an allowed value
            // but was written within the form definition by hand (and therefore contains a hmac),
            // check if $value is manipulated.
            // If $value has no hmac or if the hmac exists but is not valid,
            // then $this->validatePropertyCollectionElementPropertyValueByHmacData() will
            // throw an exception.
            $this->validateFormElementPropertyValueByHmacData(
                $this->currentElement,
                $value,
                $this->sessionToken,
                $dto
            );
        }
    }
}
