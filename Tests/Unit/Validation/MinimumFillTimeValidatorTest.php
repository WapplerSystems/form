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
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Security\FormChallengeService;
use TYPO3\CMS\Form\Validation\MinimumFillTimeValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MinimumFillTimeValidatorTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private FormChallengeService $challengeService;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->challengeService = new FormChallengeService(new HashService());
        // The validator reaches for the service through makeInstance(), because
        // Extbase's ValidatorResolver does not autowire validator constructors.
        GeneralUtility::setSingletonInstance(FormChallengeService::class, $this->challengeService);
    }
    /**
     * Pushes exactly one LanguageServiceFactory stub for a single rejection that
     * has to go through `LLL:` resolution. Not done for every test: the testing
     * framework fails a test that leaves an unconsumed instance behind, and most
     * tests here pass a literal message so no resolution happens at all.
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
     * @param array<string, mixed> $options
     * @param array<string, mixed> $parsedBody
     */
    private function buildValidator(array $options, array $parsedBody): MinimumFillTimeValidator
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParsedBody')->willReturn($parsedBody);

        $formDefinition = $this->createStub(FormDefinition::class);
        $formDefinition->method('getIdentifier')->willReturn('ContactForm');

        $formRuntime = $this->createStub(FormRuntime::class);
        $formRuntime->method('getRequest')->willReturn($request);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);

        // A literal message keeps the LLL: resolution (and therefore
        // LanguageServiceFactory, which is not autowirable here) out of the
        // way; the fallback to the shipped default has its own test.
        $validator = new MinimumFillTimeValidator();
        $validator->setOptions($options + ['errorMessage' => 'rejected']);
        $validator->setFormRuntime($formRuntime);

        return $validator;
    }

    #[Test]
    public function submissionSlowerThanTheMinimumIsAccepted(): void
    {
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [FormChallengeService::FILL_TIME_FIELD => '5001']
        );

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function submissionFasterThanTheMinimumIsRejected(): void
    {
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [FormChallengeService::FILL_TIME_FIELD => '1200']
        );

        self::assertTrue($validator->validate([])->hasErrors());
    }

    #[Test]
    public function exactlyTheMinimumIsAccepted(): void
    {
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [FormChallengeService::FILL_TIME_FIELD => '5000']
        );

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function missingMeasurementIsRejectedByDefault(): void
    {
        // The JavaScript-disabled / stripped-field case.
        $validator = $this->buildValidator(['minimumSeconds' => 5], []);

        self::assertTrue($validator->validate([])->hasErrors());
    }

    #[Test]
    public function missingMeasurementPassesWhenMissingDataIsAllowed(): void
    {
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5, 'allowMissingTimingData' => true],
            []
        );

        self::assertFalse($validator->validate([])->hasErrors());
    }

    /**
     * A value that is not a plain integer counts as "no measurement" rather than
     * being coerced — otherwise "9999s" or "1e9" would sail through a lenient cast.
     *
     * @return array<string, array{mixed}>
     */
    public static function unusableMeasurementProvider(): array
    {
        return [
            'empty string' => [''],
            'unit suffix' => ['9999s'],
            'exponent' => ['1e9'],
            'negative' => ['-1'],
            'float' => ['5000.5'],
            'array' => [['5000']],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('unusableMeasurementProvider')]
    public function unusableMeasurementCountsAsMissing(mixed $submitted): void
    {
        $rejecting = $this->buildValidator(
            ['minimumSeconds' => 5],
            [FormChallengeService::FILL_TIME_FIELD => $submitted]
        );
        self::assertTrue($rejecting->validate([])->hasErrors());

        $lenient = $this->buildValidator(
            ['minimumSeconds' => 5, 'allowMissingTimingData' => true],
            [FormChallengeService::FILL_TIME_FIELD => $submitted]
        );
        self::assertFalse($lenient->validate([])->hasErrors());
    }

    #[Test]
    public function minimumOfZeroDisablesTheCheckEntirely(): void
    {
        $validator = $this->buildValidator(['minimumSeconds' => 0], []);

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function freshChallengeTokenOverrulesAFakedMeasurement(): void
    {
        // The bot case: claim ten minutes of fill time, but the server issued the
        // challenge one second ago — the form cannot have been on screen longer
        // than it has existed.
        $token = $this->challengeService->createToken('ContactForm', time() - 1);

        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [
                FormChallengeService::FILL_TIME_FIELD => '600000',
                FormChallengeService::RESPONSE_FIELD => $token,
            ]
        );

        self::assertTrue($validator->validate([])->hasErrors());
    }

    #[Test]
    public function oldChallengeTokenDoesNotInterfere(): void
    {
        $token = $this->challengeService->createToken('ContactForm', time() - 3600);

        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [
                FormChallengeService::FILL_TIME_FIELD => '6000',
                FormChallengeService::RESPONSE_FIELD => $token,
            ]
        );

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function unverifiableChallengeTokenIsIgnoredHere(): void
    {
        // Reporting a forged or foreign token is ChallengeValidator's job; this
        // validator must not turn it into a fill-time error.
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5],
            [
                FormChallengeService::FILL_TIME_FIELD => '6000',
                FormChallengeService::RESPONSE_FIELD => 'not.a-token',
            ]
        );

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function errorMessageFromTheOptionsIsUsed(): void
    {
        $validator = $this->buildValidator(
            ['minimumSeconds' => 5, 'errorMessage' => 'Zu schnell.'],
            [FormChallengeService::FILL_TIME_FIELD => '10']
        );

        $errors = $validator->validate([])->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('Zu schnell.', $errors[0]->getMessage());
    }

    #[Test]
    public function anEmptyErrorMessageFallsBackToTheShippedDefault(): void
    {
        // The form editor writes what is in the field, so an editor clearing the
        // message must not produce a rejection with no text at all.
        $this->stubLanguageServiceOnce();
        $validator = $this->buildValidator(['minimumSeconds' => 5, 'errorMessage' => ''], [FormChallengeService::FILL_TIME_FIELD => '10']);

        $errors = $validator->validate([])->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.MinimumFillTime.errorMessage',
            $errors[0]->getMessage()
        );
    }
}
