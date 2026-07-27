<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Fixtures\Forms;

/**
 * Form-definition array for the EntropySpam / validation-logging functional
 * tests. Consumed by ArrayFormFactory::build() (like every other functional
 * form test), so it is a PHP array rather than a *.form.yaml on disk.
 *
 * It mirrors the RELEVANT rendering options of the live contact form
 * (EXT:t3-template/Resources/Private/Forms/Contact.form.yaml): the EntropySpam
 * form-level validator at maximumEntropyRatio 0.85 and, optionally, the
 * recordValidationFailures opt-in. Keep `maximumEntropyRatio` in sync with the
 * live form if that value changes.
 *
 * All fields are intentionally OPTIONAL so the tests isolate the EntropySpam
 * behaviour — the only errors a submission can produce come from the validator
 * under test, not from required-field checks.
 */
final class EntropyContactForm
{
    public const IDENTIFIER = 'entropy-contact';

    public const ERROR_MESSAGE = 'Ihre Anfrage konnte nicht verarbeitet werden. Bitte formulieren Sie Ihre Nachricht anders und versuchen Sie es erneut.';

    /**
     * @return array<string, mixed>
     */
    public static function definition(bool $recordValidationFailures = true, float $maximumEntropyRatio = 0.85): array
    {
        $renderingOptions = [
            'formLevelValidators' => [
                [
                    'identifier' => 'EntropySpam',
                    'options' => [
                        'maximumEntropyRatio' => $maximumEntropyRatio,
                        'errorMessage' => self::ERROR_MESSAGE,
                    ],
                ],
            ],
        ];
        if ($recordValidationFailures) {
            $renderingOptions['recordValidationFailures'] = true;
        }

        return [
            'type' => 'Form',
            'identifier' => self::IDENTIFIER,
            'label' => 'Kontaktformular (EntropySpam fixture)',
            'prototypeName' => 'standard',
            'renderingOptions' => $renderingOptions,
            'renderables' => [
                [
                    'type' => 'Page',
                    'identifier' => 'page-1',
                    'label' => 'Kontakt',
                    'renderables' => [
                        // Fixed-choice element: its value must NOT be analysed for entropy.
                        [
                            'type' => 'SingleSelect',
                            'identifier' => 'topic',
                            'label' => 'Thema',
                            'properties' => [
                                'options' => [
                                    'typo3' => 'TYPO3',
                                    'other' => 'Sonstiges',
                                ],
                            ],
                        ],
                        // Free-text elements (text/email/telephone/textarea) ARE analysed.
                        ['type' => 'Text', 'identifier' => 'name', 'label' => 'Name'],
                        ['type' => 'Email', 'identifier' => 'email', 'label' => 'E-Mail'],
                        ['type' => 'Telephone', 'identifier' => 'phone', 'label' => 'Telefon'],
                        ['type' => 'Textarea', 'identifier' => 'message', 'label' => 'Nachricht'],
                    ],
                ],
            ],
        ];
    }
}
