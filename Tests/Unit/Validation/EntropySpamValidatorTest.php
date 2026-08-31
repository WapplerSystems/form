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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Tests\Fixtures\SpamCorpus;
use TYPO3\CMS\Form\Validation\EntropySpamValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class EntropySpamValidatorTest extends UnitTestCase
{
    /**
     * A literal message keeps the `LLL:` resolution of the shipped default (and
     * with it LanguageServiceFactory, which is not autowirable here) out of the
     * way. Every rejecting test would otherwise need a stub; the default itself
     * is covered by shippedDefaultMessageIsTranslated().
     *
     * @param array<string, mixed> $options
     */
    private function buildValidator(array $options = [], ?FormRuntime $formRuntime = null): EntropySpamValidator
    {
        $validator = new EntropySpamValidator();
        $validator->setOptions($options + ['errorMessage' => 'rejected']);
        if ($formRuntime !== null) {
            $validator->setFormRuntime($formRuntime);
        }
        return $validator;
    }

    /**
     * Pushes exactly one LanguageServiceFactory stub, for the single test that
     * lets the shipped `LLL:` default through: the testing framework fails a
     * test that leaves an unconsumed instance behind.
     */
    private function stubLanguageServiceOnce(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnArgument(0);
        $factory = $this->createStub(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);
        GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);
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
        return SpamCorpus::botTokens();
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
        return SpamCorpus::humanSubmissions();
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

    /**
     * With no errorMessage configured the validator falls back to the shipped
     * default, which is an `LLL:` reference and has to be resolved rather than
     * shown to the visitor verbatim.
     */
    #[Test]
    public function shippedDefaultMessageIsTranslated(): void
    {
        $this->stubLanguageServiceOnce();

        $validator = new EntropySpamValidator();
        $validator->setOptions([]);

        $result = $validator->validate(['message' => 'vOYhcWlrcTafTMSelBkM']);

        self::assertTrue($result->hasErrors());
        self::assertStringStartsWith(
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:',
            $result->forProperty('message')->getFirstError()->getMessage(),
        );
    }
}
