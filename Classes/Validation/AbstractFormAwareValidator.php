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

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Convenience base class for form-aware (cross-field) validators. Implements
 * the interface and exposes a getSubmittedValues() helper so concrete
 * validators don't have to walk through FormRuntime->getFormState() each time,
 * plus resolveMessage() for translatable option values.
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

    /**
     * The message for a rejection, taken from the named option and resolved for
     * the active site language.
     *
     * Falls back to the option's own declared default when the configured value
     * is empty. That is not paranoia: the form editor writes what is in the
     * field, and a `predefinedDefaults` entry or an editor clearing the input
     * can leave an empty string behind — which would otherwise override the
     * shipped, translated default and reject the submission with no explanation
     * at all.
     */
    protected function resolveErrorMessage(string $optionName = 'errorMessage'): string
    {
        $message = (string)($this->options[$optionName] ?? '');
        if (trim($message) === '') {
            $message = (string)($this->supportedOptions[$optionName][0] ?? '');
        }
        return $this->resolveMessage($message);
    }

    /**
     * Resolves a validator option that may hold an `LLL:` reference instead of a
     * literal string, against the active site language.
     *
     * Element validators get their messages translated through the form's XLF
     * chain (properties.validationErrorMessages). Form-level validators are not
     * part of that chain — their options come straight from the form definition —
     * so without this a multi-language site could only ever show one hardcoded
     * language. Non-`LLL:` values and unresolvable references are returned
     * unchanged, so a plain string in the YAML keeps working.
     */
    protected function resolveMessage(string $message): string
    {
        if (!str_starts_with($message, 'LLL:')) {
            return $message;
        }

        $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
        $siteLanguage = isset($this->formRuntime) ? $this->formRuntime->getCurrentSiteLanguage() : null;
        $languageService = $siteLanguage !== null
            ? $languageServiceFactory->createFromSiteLanguage($siteLanguage)
            : $languageServiceFactory->create('default');

        return $languageService->sL($message) ?: $message;
    }
}
