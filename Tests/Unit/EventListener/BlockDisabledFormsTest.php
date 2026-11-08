<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\Event\AfterFormRenderedEvent;
use TYPO3\CMS\Form\Event\BeforeFinishersInvokedEvent;
use TYPO3\CMS\Form\EventListener\BlockDisabledForms;
use TYPO3\CMS\Form\Service\FormDisabledNoticeRenderer;
use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BlockDisabledFormsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function anOpenFormIsRenderedUnchanged(): void
    {
        $event = new AfterFormRenderedEvent($this->formRuntime(), '<form>original</form>');
        $this->subject(disabled: false)->replaceMarkup($event);

        self::assertSame('<form>original</form>', $event->renderedContent);
    }

    #[Test]
    public function aBlockedFormIsReplacedByTheNotice(): void
    {
        $event = new AfterFormRenderedEvent($this->formRuntime(), '<form>original</form>');
        $this->subject(disabled: true)->replaceMarkup($event);

        self::assertSame('<p>notice</p>', $event->renderedContent);
    }

    /**
     * The whole point of the cache bookkeeping: while a form is blocked the
     * response must not be cached, or the notice would outlive the block — and
     * with the backend-user bypass below, a working form would be cached for
     * anonymous visitors.
     */
    #[Test]
    public function aBlockedFormMarksTheRequestUncacheable(): void
    {
        $instruction = new \TYPO3\CMS\Frontend\Cache\CacheInstruction();
        $event = new AfterFormRenderedEvent($this->formRuntime(cacheInstruction: $instruction), '<form>x</form>');

        $this->subject(disabled: true)->replaceMarkup($event);

        self::assertFalse($instruction->isCachingAllowed());
    }

    /**
     * Tagging happens even while nothing is blocked — those are exactly the
     * cached pages that have to be dropped when the switch is turned on.
     */
    #[Test]
    public function everyRenderedFormTagsThePage(): void
    {
        $collector = new CacheDataCollector();
        $event = new AfterFormRenderedEvent($this->formRuntime(collector: $collector), '<form>x</form>');

        $this->subject(disabled: false)->replaceMarkup($event);

        $tags = array_map(static fn($tag) => $tag->name, $collector->getCacheTags());
        self::assertContains('tx_form', $tags);
        self::assertContains('tx_form_contact', $tags);
    }

    #[Test]
    public function aLoggedInBackendUserStillSeesTheForm(): void
    {
        $event = new AfterFormRenderedEvent($this->formRuntime(), '<form>original</form>');
        $this->subject(disabled: true, backendUser: true)->replaceMarkup($event);

        self::assertSame('<form>original</form>', $event->renderedContent);
    }

    /**
     * The form editor renders its preview through the same runtime. Showing the
     * outage notice there would hide the form from the person trying to fix it.
     */
    #[Test]
    public function theBackendPreviewIsNeverBlocked(): void
    {
        $event = new AfterFormRenderedEvent($this->formRuntime(frontend: false), '<form>original</form>');
        $this->subject(disabled: true)->replaceMarkup($event);

        self::assertSame('<form>original</form>', $event->renderedContent);
    }

    /**
     * The guard that actually stops anything: FormRuntime::render() returns the
     * finisher output before AfterFormRenderedEvent is ever dispatched, so a
     * markup swap alone would let every mail through.
     */
    #[Test]
    public function aSubmissionToABlockedFormIsRejected(): void
    {
        $result = new Result();
        $event = new AfterFormIsValidatedEvent(
            $this->createStub(Page::class),
            $this->formRuntime(),
            $this->createStub(RequestInterface::class),
            $result
        );

        $this->subject(disabled: true)->blockSubmission($event);

        self::assertTrue($result->hasErrors());
        self::assertSame('notice text', $result->forProperty('contact')->getErrors()[0]->getMessage());
    }

    #[Test]
    public function aSubmissionToAnOpenFormIsUntouched(): void
    {
        $result = new Result();
        $event = new AfterFormIsValidatedEvent(
            $this->createStub(Page::class),
            $this->formRuntime(),
            $this->createStub(RequestInterface::class),
            $result
        );

        $this->subject(disabled: false)->blockSubmission($event);

        self::assertFalse($result->hasErrors());
    }

    #[Test]
    public function theFinisherChainIsCancelledForABlockedForm(): void
    {
        $finisherContext = $this->createMock(FinisherContext::class);
        $finisherContext->expects(self::once())->method('cancel');

        $this->subject(disabled: true)->cancelFinishers(
            new BeforeFinishersInvokedEvent($this->formRuntime(), [], $finisherContext)
        );
    }

    #[Test]
    public function theFinisherChainRunsForAnOpenForm(): void
    {
        $finisherContext = $this->createMock(FinisherContext::class);
        $finisherContext->expects(self::never())->method('cancel');

        $this->subject(disabled: false)->cancelFinishers(
            new BeforeFinishersInvokedEvent($this->formRuntime(), [], $finisherContext)
        );
    }

    /**
     * Real FormKillSwitch and FormDisabledNoticeRenderer over stubbed
     * infrastructure rather than test doubles: both are final, and more to the
     * point, doubling them here would test the listener against an idealised
     * switch instead of the one that ships.
     */
    private function subject(bool $disabled, bool $backendUser = false): BlockDisabledForms
    {
        $killSwitch = new FormKillSwitch($this->registry($disabled), $this->createStub(CacheManager::class));
        $renderer = new FormDisabledNoticeRenderer($this->viewFactory(), $this->languageServiceFactory());

        $context = new Context();
        $context->setAspect('backend.user', new UserAspect($backendUser ? $this->backendUser() : null));

        return new BlockDisabledForms($killSwitch, $renderer, $context);
    }

    private function registry(bool $disabled): Registry
    {
        $entry = $disabled
            ? [FormKillSwitch::SCOPE_FORM => ['contact' => ['disabled' => true, 'message' => '', 'until' => null]]]
            : [];

        $registry = $this->createStub(Registry::class);
        $registry->method('get')->willReturn($entry);

        return $registry;
    }

    private function viewFactory(): ViewFactoryInterface
    {
        $view = $this->createStub(ViewInterface::class);
        $view->method('render')->willReturn('<p>notice</p>');

        $factory = $this->createStub(ViewFactoryInterface::class);
        $factory->method('create')->willReturn($view);

        return $factory;
    }

    private function languageServiceFactory(): LanguageServiceFactory
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturn('notice text');

        $factory = $this->createStub(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);

        return $factory;
    }

    private function backendUser(): object
    {
        $user = new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication();
        $user->user = ['uid' => 1, 'username' => 'claude'];

        return $user;
    }

    private function formRuntime(
        bool $frontend = true,
        ?CacheDataCollector $collector = null,
        ?object $cacheInstruction = null,
    ): FormRuntime {
        $formDefinition = $this->createStub(FormDefinition::class);
        $formDefinition->method('getIdentifier')->willReturn('contact');

        $attributes = [
            'applicationType' => $frontend
                ? SystemEnvironmentBuilder::REQUESTTYPE_FE
                : SystemEnvironmentBuilder::REQUESTTYPE_BE,
            'frontend.cache.collector' => $collector,
            'frontend.cache.instruction' => $cacheInstruction,
            'site' => null,
        ];

        $request = $this->createStub(RequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn(string $name, mixed $default = null): mixed => $attributes[$name] ?? $default
        );

        $formRuntime = $this->createStub(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);
        $formRuntime->method('getRequest')->willReturn($request);

        return $formRuntime;
    }
}
