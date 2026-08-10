<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Validation\EntropySpamValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class EntropySpamValidatorTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    private function buildValidator(array $options = [], ?FormRuntime $formRuntime = null): EntropySpamValidator
    {
        $validator = new EntropySpamValidator();
        $validator->setOptions($options);
        if ($formRuntime !== null) {
            $validator->setFormRuntime($formRuntime);
        }
        return $validator;
    }

    /**
     * @param array<string, string> $typesByIdentifier
     */
    private function formRuntimeWithElementTypes(array $typesByIdentifier): FormRuntime
    {
        $definition = $this->createMock(FormDefinition::class);
        $definition->method('getElementByIdentifier')->willReturnCallback(
            function (string $identifier) use ($typesByIdentifier): ?FormElementInterface {
                if (!array_key_exists($identifier, $typesByIdentifier)) {
                    return null;
                }
                $element = $this->createMock(FormElementInterface::class);
                $element->method('getType')->willReturn($typesByIdentifier[$identifier]);
                return $element;
            }
        );
        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($definition);
        return $formRuntime;
    }

    #[Test]
    public function randomGibberishInFreeTextIsRejected(): void
    {
        // The reporting sample: random-looking name and message.
        $values = [
            'subject' => 'TYPO3',
            'name' => 'vOYhcWlrcTafTMSelBkM',
            'email' => 'o.g.i.paso.60.6@gmail.com',
            'phone' => '4194840183',
            'message' => 'goIuMokMxpTCeKnJfIq',
        ];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function gibberishTokenProvider(): array
    {
        return [
            'random mixed case' => ['vOYhcWlrcTafTMSelBkM'],
            'random mixed case 2' => ['goIuMokMxpTCeKnJfIq'],
            'all upper consonants' => ['XQVBLMPKWZRNTCYHF'],
            'lowercase keyboard mash' => ['asdkjhqweuiozxcvbnm'],
            'random camel case' => ['aSdKjHqWeUiOzXcVbN'],
        ];
    }

    #[Test]
    #[DataProvider('gibberishTokenProvider')]
    public function gibberishTokensAreRejected(string $token): void
    {
        self::assertTrue(
            $this->buildValidator()->validate(['message' => $token])->hasErrors(),
            sprintf('Expected "%s" to be rejected as gibberish.', $token),
        );
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function legitimateSubmissionProvider(): array
    {
        return [
            'normal contact' => [[
                'name' => 'Maria Lindqvist',
                'email' => 'maria@example.com',
                'message' => 'Bitte rufen Sie mich am Montag zurueck, vielen Dank!',
            ]],
            'hyphenated name' => [[
                'name' => 'Wolfgang Schmidt-Hubermann',
                'message' => 'Ich interessiere mich fuer Ihr Angebot.',
            ]],
            'german compound word' => [[
                'message' => 'Donaudampfschifffahrtsgesellschaft',
            ]],
            'lowercase long word' => [[
                'message' => 'unternehmensberatung',
            ]],
            'all-caps words' => [[
                'name' => 'WOLFGANG ABTEILUNGSLEITER',
            ]],
            'url in message' => [[
                'message' => 'Siehe https://example.com/produkte fuer Details.',
            ]],
        ];
    }

    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('legitimateSubmissionProvider')]
    public function legitimateSubmissionsAreAccepted(array $values): void
    {
        self::assertFalse(
            $this->buildValidator()->validate($values)->hasErrors(),
            'Legitimate submission was wrongly rejected: ' . json_encode($values),
        );
    }

    #[Test]
    public function repetitiveLowEntropyTextIsRejected(): void
    {
        // Below minimumEntropy and long enough to pass the minimumLength gate.
        $values = ['message' => str_repeat('a', 40)];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
    }

    #[Test]
    public function emptySubmissionIsAccepted(): void
    {
        self::assertFalse($this->buildValidator()->validate([])->hasErrors());
    }

    #[Test]
    public function valuesFromFixedChoiceElementsAreNotAnalysed(): void
    {
        // A (deliberately gibberish) value coming from a fixed-choice element
        // must be ignored, so the submission is accepted.
        $formRuntime = $this->formRuntimeWithElementTypes([
            'topic' => 'SingleSelect',
            'interests' => 'MultiCheckbox',
        ]);
        $values = [
            'topic' => 'vOYhcWlrcTafTMSelBkM',
            'interests' => 'goIuMokMxpTCeKnJfIq',
        ];

        self::assertFalse($this->buildValidator([], $formRuntime)->validate($values)->hasErrors());
    }

    #[Test]
    public function gibberishInFreeTextElementIsRejectedWithFormContext(): void
    {
        $formRuntime = $this->formRuntimeWithElementTypes([
            'topic' => 'SingleSelect',
            'name' => 'Text',
        ]);
        $values = [
            'topic' => 'support',
            'name' => 'vOYhcWlrcTafTMSelBkM',
        ];

        self::assertTrue($this->buildValidator([], $formRuntime)->validate($values)->hasErrors());
    }

    #[Test]
    public function whitelistRestrictsAnalysisToGivenIdentifiers(): void
    {
        // Only "comment" is analysed; the gibberish in "ref" is ignored.
        $validator = $this->buildValidator(['textFieldIdentifiers' => ['comment']]);
        $values = [
            'ref' => 'vOYhcWlrcTafTMSelBkM',
            'comment' => 'Vielen Dank fuer die schnelle Bearbeitung.',
        ];

        self::assertFalse($validator->validate($values)->hasErrors());
    }
}
