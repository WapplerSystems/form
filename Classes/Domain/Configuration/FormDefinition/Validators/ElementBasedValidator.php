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
abstract class ElementBasedValidator extends AbstractValidator
{
    /**
     * Throws an exception if value from a form element property
     * does not match its hmac hash or if there is no hmac hash
     * available for the value.
     *
     * @param mixed $value
     * @throws PropertyException
     */
    public function validateFormElementPropertyValueByHmacData(
        array $currentElement,
        $value,
        string $sessionToken,
        ValidationDto $dto
    ): void {
        $hmacDataPath = $this->buildHmacDataPath($dto->getPropertyPath());
        if (ArrayUtility::isValidPath($currentElement, $hmacDataPath, '.')) {
            $hmacData = ArrayUtility::getValueByPath($currentElement, $hmacDataPath, '.');

            $hmacContent = [$dto->getFormElementIdentifier(), $dto->getPropertyPath()];
            if (!$this->getFormDefinitionValidationService()->isPropertyValueEqualToHistoricalValue($hmacContent, $value, $hmacData, $sessionToken)) {
                $message = 'The value "%s" of property "%s" (form element "%s") is not equal to the historical value "%s" #1528588036';
                throw new PropertyException(
                    sprintf(
                        $message,
                        $value,
                        $dto->getPropertyPath(),
                        $dto->getFormElementIdentifier(),
                        $hmacData['value'] ?? ''
                    ),
                    1528588036
                );
            }
        } else {
            $message = 'No hmac found for property "%s" (form element "%s") #1528588037';
            throw new PropertyException(
                sprintf($message, $dto->getPropertyPath(), $dto->getFormElementIdentifier()),
                1528588037
            );
        }
    }

    /**
     * Throws an exception if a (not yet hmac-secured) property value does not
     * match the predefined default value from the form editor setup.
     *
     * For newly created elements there is no hmac for the value yet, so the
     * integrity is checked by comparing the submitted $value (form definition)
     * with $predefinedDefaultValue (form setup). Shared by the creatable and the
     * non-creatable (WapplerSystems fork) property validators.
     *
     * @param mixed $value
     * @throws PropertyException
     */
    protected function validateFormElementPredefinedDefaultValue(
        $value,
        ValidationDto $dto
    ): void {
        // If the form element is newly created, we have to compare the $value (form definition) with $predefinedDefaultValue (form setup)
        // to check the integrity (at this time we don't have a hmac for the $value to check the integrity)
        $predefinedDefaultValue = $this->getConfigurationService()->getFormElementPredefinedDefaultValueFromFormEditorSetup($dto);
        if ($value !== $predefinedDefaultValue) {
            $throwException = true;

            if (is_string($predefinedDefaultValue)) {
                // Last chance:
                // Get all translations (from all backend languages) for the untranslated! $predefinedDefaultValue and
                // compare the (already translated) $value (from the form definition) against the possible
                // translations from $predefinedDefaultValue.
                // Usecase:
                //   * backend language is EN
                //   * open the form editor and add a ContentElement form element
                //   * switch to another browser tab and change the backend language to DE
                //   * clear the cache
                //   * go back to the form editor and click the save button
                // Out of scope:
                //   * the same scenario as above + delete the previous chosen backend language within the maintenance tool
                $untranslatedPredefinedDefaultValue = $this->getConfigurationService()->getFormElementPredefinedDefaultValueFromFormEditorSetup($dto, false);
                $translations = $this->getConfigurationService()->getAllBackendTranslationsForTranslationKey(
                    $untranslatedPredefinedDefaultValue,
                    $dto->getPrototypeName()
                );

                if (in_array($value, $translations, true)) {
                    $throwException = false;
                }
            }

            if ($throwException) {
                $message = 'The value "%s" of property "%s" (form element "%s") is not equal to the default value "%s" #1528588035';
                throw new PropertyException(
                    sprintf(
                        $message,
                        $value,
                        $dto->getPropertyPath(),
                        $dto->getFormElementIdentifier(),
                        $predefinedDefaultValue
                    ),
                    1528588035
                );
            }
        }
    }
}
