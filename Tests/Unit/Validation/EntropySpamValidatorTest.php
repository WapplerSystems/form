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
            'consonant heavy compound' => [[
                'message' => 'Brandschutzklappe',
            ]],
            'compound with low vowel ratio' => [[
                'subject' => 'Bildschirmfoto',
            ]],
            'short enquiry naming a test mailbox' => [[
                'message' => 'Die Absenderadresse ist ein Testpostfach.',
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

    /**
     * The reported regression: a genuine German enquiry was turned away because
     * one compound word looks random on its own. "Testpostfach" is twelve
     * letters, has a normalized entropy of 0.907 and a vowel ratio of 0.25, so
     * the per-token verdict flagged it — and with it the whole submission.
     */
    #[Test]
    public function oneGibberishLookingWordInALongTextIsAccepted(): void
    {
        $values = [
            'subject' => 'Frage zum Support-Formular',
            'message' => 'Dies ist ein Test von uns. Bitte ignorieren und nicht bearbeiten. '
                . 'Hintergrund: Das Support-Formular auf der Kontaktseite wird per JavaScript '
                . 'nachgeladen. Dadurch war der Spam-Schutz zeitweise fehlerhaft und hat '
                . 'Einsendungen abgewiesen. Die angegebene Versionsnummer ist ein Platzhalter, '
                . 'die Absenderadresse ist ein Testpostfach. Eine Antwort ist nicht noetig.',
        ];

        self::assertFalse(
            $this->buildValidator()->validate($values)->hasErrors(),
            'A long, plainly human message was rejected over a single compound word.',
        );
    }

    #[Test]
    public function gibberishWrappedInAGreetingIsStillRejected(): void
    {
        // Short and almost entirely salad — the share stays high, so padding a
        // random token with a couple of words does not buy a way past the check.
        $values = ['message' => 'Hello vOYhcWlrcTafTMSelBkM best regards'];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
    }

    #[Test]
    public function shareThresholdIsConfigurable(): void
    {
        $values = ['message' => 'Hello vOYhcWlrcTafTMSelBkM best regards'];

        self::assertFalse(
            $this->buildValidator(['gibberishShare' => 0.9])->validate($values)->hasErrors(),
            'A share above the measured 0.556 should let this submission through.',
        );
    }

    /**
     * Entropy and vowel ratio alone are not enough to tell a German compound
     * from a random string — measured against the hunspell de_DE list they flag
     * 3.44% of all words of twelve letters or more. Requiring an over-long
     * consonant run as well brings that down to 0.21% while still catching
     * every known spam sample, because natural words stay syllabic.
     */
    #[Test]
    public function syllabicWordsSurviveEvenWhenTheyLookRandom(): void
    {
        // Normalized entropy 0.942, vowel ratio 0.24 — both in gibberish
        // territory. Its longest consonant run is 5 ("ndsch"), so it passes.
        $values = ['message' => 'Brandschutzklappe'];

        self::assertFalse($this->buildValidator()->validate($values)->hasErrors());
    }

    #[Test]
    public function raisingTheConsonantRunThresholdLetsGibberishThrough(): void
    {
        // Guards the default: the reported sample peaks at a run of six, so a
        // threshold of six or more stops rejecting it.
        $values = ['message' => 'goIuMokMxpTCeKnJfIq'];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
        self::assertFalse(
            $this->buildValidator(['maximumConsonantRun' => 6])->validate($values)->hasErrors(),
        );
    }

    /**
     * The submission that got through on a live site and is the reason the
     * alphanumeric token check exists. Every field is salad with a digit or two
     * dropped in, which is exactly what defeated the letter-only tokenizer: it
     * cut "ZYWVj7hyXv" into "ZYWVj" and "hyXv", both far too short to judge, so
     * the gibberish share came out at 0.00 while the combined entropy — 5.07
     * bits per character — sat comfortably inside the permitted 1.8 to 5.8 band.
     */
    #[Test]
    public function digitSeededGibberishAcrossEveryFieldIsRejected(): void
    {
        $values = [
            'text-1' => 'ZYWVj7hyXv',
            'text-2' => 'AmJj19D9Y5',
            'email-1' => 'qsdixon@yahoo.com',
            'text-3' => '9KI0nB1YVM',
            'text-4' => 'JuT8l9hsQJ',
        ];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function mixedAlnumGibberishTokenProvider(): array
    {
        return [
            'reported name' => ['ZYWVj7hyXv'],
            'reported first name' => ['AmJj19D9Y5'],
            'reported phone' => ['9KI0nB1YVM'],
            'reported contract designation' => ['JuT8l9hsQJ'],
            'digit in the middle of camel salad' => ['aBcDeFgHiJ7kLmNoPqRsT'],
            'alternating without case flip' => ['q1w2e3r4t5'],
            'short mixed salad' => ['xk3Mp9Qz'],
        ];
    }

    #[Test]
    #[DataProvider('mixedAlnumGibberishTokenProvider')]
    public function mixedAlphanumericGibberishIsRejected(string $token): void
    {
        self::assertTrue(
            $this->buildValidator()->validate(['message' => $token])->hasErrors(),
            sprintf('Expected "%s" to be rejected as machine-generated.', $token),
        );
    }

    /**
     * The expensive direction. A letters-and-digits token is normal in a
     * contract designation, a customer number, an IBAN or a course name, and a
     * cancellation form that turns those away costs the site owner a deadline,
     * not a spam mail.
     *
     * @return array<string, array{array<string, string>}>
     */
    public static function legitimateAlnumSubmissionProvider(): array
    {
        return [
            'contract number' => [['message' => 'Vertrag Nr. AS-2024-1234']],
            'course with year' => [['message' => 'Examenskurs2026']],
            'course with term' => [['message' => 'Assessorkurs 2026/1']],
            'iban' => [['message' => 'DE89370400440532013000']],
            'customer number' => [['message' => 'MITGLIEDSNR 4711']],
            'abbreviation with digit' => [['message' => 'BGB-AT2']],
            'product with digits' => [['message' => 'iPhone13']],
            'years spanned' => [['message' => 'Vertrag2019bis2026']],
            'inner capital and year' => [['message' => 'StudentIn2026']],
            'phone number' => [['phone' => '0170 1234567']],
            'phone number international' => [['phone' => '+49 (0)89 123456-78']],
            'phone number bare' => [['phone' => '4194840183']],
            'order number with suffix' => [['message' => 'Kurs-Nr 8823-A']],
        ];
    }

    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('legitimateAlnumSubmissionProvider')]
    public function legitimateAlphanumericValuesAreAccepted(array $values): void
    {
        self::assertFalse(
            $this->buildValidator()->validate($values)->hasErrors(),
            'Legitimate alphanumeric value was wrongly rejected: ' . json_encode($values),
        );
    }

    /**
     * Guards the reason digits-only tokens are excluded explicitly rather than
     * falling through to the letter-only test: longestConsonantRun() counts
     * every character that is not a vowel, so "4194840183" would score a run of
     * its full length and a vowel ratio of zero — a rejected phone number. The
     * old tokenizer only avoided this by discarding digits altogether.
     */
    #[Test]
    public function aPhoneNumberOnItsOwnIsNotGibberish(): void
    {
        self::assertFalse(
            $this->buildValidator()->validate(['phone' => '4194840183'])->hasErrors(),
            'A bare phone number must not count as machine-generated.',
        );
    }

    /**
     * Why gibberishTokenLength dropped from twelve to eight: the obvious next
     * move for a bot that has just lost the digits is a ten-letter random name,
     * and at twelve that was not even looked at.
     */
    #[Test]
    public function shortLetterOnlyRandomTokensAreRejected(): void
    {
        self::assertTrue(
            $this->buildValidator()->validate(['name' => 'Xkfjqwlrbn'])->hasErrors(),
            'A ten-letter random name must not pass just because it is short.',
        );
    }

    /**
     * The counterpart: eight letters is where real German surnames live, and the
     * consonant-run condition — not the length — is what keeps them out of the
     * verdict. "Schwerdt" has a normalized entropy of 1.0 and no repeated
     * letter at all, so entropy alone would flag it; its longest consonant run
     * is four.
     */
    #[Test]
    public function shortConsonantHeavySurnamesSurviveTheLoweredThreshold(): void
    {
        foreach (['Schwerdt', 'Schlumpf', 'Schrumpft', 'Pfeiffer'] as $surname) {
            self::assertFalse(
                $this->buildValidator()->validate(['name' => $surname])->hasErrors(),
                sprintf('Surname "%s" was wrongly rejected.', $surname),
            );
        }
    }

    #[Test]
    public function mixedAlnumThresholdsAreConfigurable(): void
    {
        $values = ['message' => 'ZYWVj7hyXv'];

        self::assertTrue($this->buildValidator()->validate($values)->hasErrors());
        self::assertFalse(
            $this->buildValidator(['mixedAlnumMinimumAlternations' => 3])->validate($values)->hasErrors(),
            'The reported sample alternates twice; demanding three must let it through.',
        );
        self::assertFalse(
            $this->buildValidator(['mixedAlnumTokenLength' => 11])->validate($values)->hasErrors(),
            'The reported sample is ten characters; a threshold above it must let it through.',
        );
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
