<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Framework;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Tests\Functional\Fixtures\Forms\EntropyContactForm;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Shared harness for the EntropySpam / validation-logging functional tests.
 *
 * Drives a real submission of the {@see EntropyContactForm} fixture through the
 * FormRuntime up to and including the `AfterFormIsValidatedEvent` dispatch, so
 * the fork's `RunFormLevelValidators` and `RecordValidationFailures` event
 * listeners run exactly as they do in production — no mocked event dispatcher.
 *
 * The submitted values are seeded as Extbase request arguments (keyed by
 * element identifier); FormRuntime::mapAndValidatePage() maps them into the
 * form state, runs per-element validation and then fires the aggregate event.
 */
abstract class EntropyFormTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'form',
    ];

    private ArrayFormFactory $formFactory;

    protected function setUp(): void
    {
        parent::setUp();
        // FormRuntime is a frontend concept — provide a minimal, valid FE request
        // (empty FrontendTypoScript) so FrontendConfigurationManager can operate.
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $feRequest = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript);
        $this->get(ExtbaseConfigurationManagerInterface::class)->setRequest($feRequest);
        $this->formFactory = $this->get(ArrayFormFactory::class);
    }

    /**
     * Submits $submittedValues (keyed by element identifier) to the fixture form
     * and returns the aggregate validation Result after the full event chain.
     *
     * @param array<string, mixed> $submittedValues
     */
    protected function validateSubmission(array $submittedValues, bool $recordValidationFailures = true): Result
    {
        $formDefinition = $this->formFactory->build(
            EntropyContactForm::definition($recordValidationFailures),
            null,
            new ServerRequest(),
        );

        $container = $this->get('service_container');
        $subject = $this->getAccessibleMock(FormRuntime::class, null, [
            $container,
            $this->createMock(ExtbaseConfigurationManagerInterface::class),
            new HashService(),
            $this->createMock(ValidatorResolver::class),
            $this->createMock(Context::class),
            $this->get(EventDispatcherInterface::class),
        ]);
        $subject->setFormDefinition($formDefinition);
        $subject->setRequest($this->buildExtbaseRequest($submittedValues));
        $subject->_call('initializeFormStateFromRequest');

        return $subject->_call('mapAndValidatePage', $formDefinition->getPageByIndex(0));
    }

    /**
     * The numeric codes of every error in the (possibly nested) Result.
     *
     * @return list<int>
     */
    protected function errorCodes(Result $result): array
    {
        $codes = [];
        foreach ($result->getFlattenedErrors() as $errors) {
            foreach ($errors as $error) {
                $codes[] = $error->getCode();
            }
        }
        return $codes;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function buildExtbaseRequest(array $arguments): Request
    {
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->initializeUserSessionManager();
        $serverRequest = (new ServerRequest())
            ->withAttribute('extbase', new ExtbaseRequestParameters())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.user', $frontendUser);
        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        return (new Request($serverRequest))
            ->withPluginName('Formframework')
            ->withArguments($arguments);
    }
}
