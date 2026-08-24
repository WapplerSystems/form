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

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Security\FormChallengeService;
use TYPO3\CMS\Form\Validation\ChallengeValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ChallengeValidatorTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private FormChallengeService $challengeService;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->challengeService = new FormChallengeService(new HashService());
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
    private function buildValidator(array $options, array $parsedBody): ChallengeValidator
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParsedBody')->willReturn($parsedBody);

        $formDefinition = $this->createStub(FormDefinition::class);
        $formDefinition->method('getIdentifier')->willReturn('ContactForm');

        $formRuntime = $this->createStub(FormRuntime::class);
        $formRuntime->method('getRequest')->willReturn($request);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);

        // Literal messages keep the LLL: resolution (and therefore
        // LanguageServiceFactory, which is not autowirable here) out of the
        // way; both fallbacks to the shipped defaults have their own test.
        $validator = new ChallengeValidator();
        $validator->setOptions($options + [
            'errorMessage' => 'rejected',
            'errorMessageScriptMissing' => 'no script ran',
        ]);
        $validator->setFormRuntime($formRuntime);

        return $validator;
    }

    #[Test]
    public function solvedChallengeIsAccepted(): void
    {
        $token = $this->challengeService->createToken('ContactForm');

        $validator = $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => $token]);

        self::assertFalse($validator->validate([])->hasErrors());
    }

    #[Test]
    public function missingResponseIsRejected(): void
    {
        // The client that never ran the JavaScript.
        self::assertTrue($this->buildValidator([], [])->validate([])->hasErrors());
        self::assertTrue(
            $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => ''])->validate([])->hasErrors()
        );
    }

    #[Test]
    public function anUntouchedSentinelIsReportedAsAMissingScript(): void
    {
        // The field is rendered holding the sentinel, so getting it back means
        // no script ran - a different problem from a wrong answer, and the only
        // one the visitor can do anything about.
        foreach ([[], [FormChallengeService::RESPONSE_FIELD => ''], [FormChallengeService::RESPONSE_FIELD => FormChallengeService::SCRIPT_MISSING_SENTINEL]] as $parsedBody) {
            $errors = $this->buildValidator([], $parsedBody)->validate([])->getErrors();

            self::assertCount(1, $errors);
            self::assertSame('no script ran', $errors[0]->getMessage());
            self::assertSame(1755648003, $errors[0]->getCode());
        }
    }

    #[Test]
    public function anUnusableAnswerKeepsTheGenericRejection(): void
    {
        $errors = $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => 'not.a.token'])
            ->validate([])->getErrors();

        self::assertCount(1, $errors);
        self::assertSame('rejected', $errors[0]->getMessage());
        self::assertSame(1755648001, $errors[0]->getCode());
    }

    #[Test]
    public function echoedChallengeIsRejected(): void
    {
        // The client that copied the value out of the markup without reversing
        // the obfuscation — the case the obfuscation exists for.
        $challenge = $this->challengeService->obfuscate($this->challengeService->createToken('ContactForm'));

        $validator = $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => $challenge]);

        self::assertTrue($validator->validate([])->hasErrors());
    }

    #[Test]
    public function tokenIssuedForAnotherFormIsRejected(): void
    {
        $token = $this->challengeService->createToken('NewsletterForm');

        $validator = $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => $token]);

        self::assertTrue($validator->validate([])->hasErrors());
    }

    #[Test]
    public function expiredTokenIsRejectedWhenAMaxAgeIsConfigured(): void
    {
        $token = $this->challengeService->createToken('ContactForm', time() - 3600);

        self::assertTrue(
            $this->buildValidator(['maxAge' => 900], [FormChallengeService::RESPONSE_FIELD => $token])
                ->validate([])->hasErrors()
        );
        // Default: no age check, because the challenge lives in the page cache.
        self::assertFalse(
            $this->buildValidator([], [FormChallengeService::RESPONSE_FIELD => $token])
                ->validate([])->hasErrors()
        );
    }

    #[Test]
    public function errorMessageFromTheOptionsIsUsed(): void
    {
        $validator = $this->buildValidator(
            ['errorMessage' => 'Bitte JavaScript aktivieren.'],
            [FormChallengeService::RESPONSE_FIELD => 'not.a.token']
        );

        $errors = $validator->validate([])->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('Bitte JavaScript aktivieren.', $errors[0]->getMessage());
    }

    #[Test]
    public function anEmptyErrorMessageFallsBackToTheShippedDefault(): void
    {
        // The form editor writes what is in the field, so an editor clearing the
        // message must not produce a rejection with no text at all.
        $this->stubLanguageServiceOnce();
        $validator = $this->buildValidator(
            ['errorMessage' => ''],
            [FormChallengeService::RESPONSE_FIELD => 'not.a.token']
        );

        $errors = $validator->validate([])->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.Challenge.errorMessage',
            $errors[0]->getMessage()
        );
    }

    #[Test]
    public function anEmptyScriptMissingMessageFallsBackToTheShippedDefault(): void
    {
        $this->stubLanguageServiceOnce();
        $validator = $this->buildValidator(['errorMessageScriptMissing' => ''], []);

        $errors = $validator->validate([])->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(
            'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formLevelValidators.Challenge.errorMessageScriptMissing',
            $errors[0]->getMessage()
        );
    }
}
