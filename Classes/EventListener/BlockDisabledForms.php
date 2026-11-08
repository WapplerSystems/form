<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\Event\AfterFormRenderedEvent;
use TYPO3\CMS\Form\Event\BeforeFinishersInvokedEvent;
use TYPO3\CMS\Form\Service\FormDisabledNoticeRenderer;
use TYPO3\CMS\Form\Service\FormKillSwitch;

/**
 * Enforces the kill switch in the frontend.
 *
 * Three hooks, and the reason there is more than one is the single most
 * expensive thing to get wrong here: FormRuntime::render() returns early —
 *
 *     if ($this->isAfterLastPage()) { return $this->invokeFinishers(); }
 *
 * — so on the FINAL submit AfterFormRenderedEvent is never dispatched. A
 * listener that only rewrites markup therefore shows a nice notice to everyone
 * who opens the page while the mails keep going out, which is the exact
 * opposite of what the switch is for. The submit-side guard is the one that
 * actually stops anything; the markup swap is what the visitor sees.
 *
 *  1. blockSubmission()  — attaches an error, so validation fails, the core
 *     resets currentPage to the last displayed one, isAfterLastPage() turns
 *     false and the finishers are never reached. Both paths then end in the
 *     same rendering, so a GET visitor and a rejected POST see one wording.
 *  2. cancelFinishers()  — backstop for any path that gets past (1).
 *  3. replaceMarkup()    — swaps the form for the notice, and does the cache
 *     bookkeeping described below.
 *
 * Caching. Only the `perform` action is registered non-cacheable; the first
 * display of a form goes into the page cache. Two consequences are handled
 * here rather than left to whoever flips the switch:
 *
 *  - Every rendered form tags its page (`tx_form`, plus one tag per form),
 *    always — including while the switch is off, because those are precisely
 *    the cached pages that have to be dropped when it is turned on. Flushing by
 *    tag instead of clearing the whole page cache is what keeps the emergency
 *    stop from becoming a second outage.
 *  - While a form is blocked the request is marked uncacheable. That keeps the
 *    notice out of the cache (so switching back on needs no flush at all, and a
 *    timed block simply expires), and it closes a real cache-poisoning hole:
 *    a logged-in backend user is allowed to see the working form, and without
 *    this that render would be cached and served to everyone.
 *
 * The backend user bypass covers the submit as well, otherwise there would be
 * no way to verify that a form works again after unblocking it.
 */
final class BlockDisabledForms
{
    /**
     * Distinct code so a project can recognise the rejection in
     * tx_form_validation_log and not mistake it for a spam-filter hit.
     */
    private const ERROR_CODE = 1757890001;

    public function __construct(
        private readonly FormKillSwitch $killSwitch,
        private readonly FormDisabledNoticeRenderer $noticeRenderer,
        private readonly Context $context,
    ) {}

    #[AsEventListener('wapplersystems-form/block-disabled-forms-submission')]
    public function blockSubmission(AfterFormIsValidatedEvent $event): void
    {
        if (!$this->blocks($event->formRuntime)) {
            return;
        }

        $formDefinition = $event->formRuntime->getFormDefinition();
        $message = $this->noticeRenderer->resolveMessage(
            $event->formRuntime,
            $this->killSwitch->getMessage($formDefinition->getIdentifier(), $this->siteIdentifier($event->formRuntime))
        );

        $event->result
            ->forProperty($formDefinition->getIdentifier())
            ->addError(new Error($message, self::ERROR_CODE));
    }

    #[AsEventListener('wapplersystems-form/block-disabled-forms-finishers')]
    public function cancelFinishers(BeforeFinishersInvokedEvent $event): void
    {
        if (!$this->blocks($event->formRuntime)) {
            return;
        }

        $event->finisherContext->cancel();
    }

    #[AsEventListener('wapplersystems-form/block-disabled-forms-rendering')]
    public function replaceMarkup(AfterFormRenderedEvent $event): void
    {
        $formRuntime = $event->formRuntime;
        $request = $formRuntime->getRequest();
        if (!$this->isFrontend($request)) {
            return;
        }

        $formIdentifier = $formRuntime->getFormDefinition()->getIdentifier();
        $this->tagPage($request, $formIdentifier);

        if (!$this->killSwitch->isDisabled($formIdentifier, $this->siteIdentifier($formRuntime))) {
            return;
        }

        // Also for the backend-user bypass below: their view must never be the
        // one that gets cached for anonymous visitors.
        $request->getAttribute('frontend.cache.instruction')?->disableCache(
            'EXT:form kill switch is active for form "' . $formIdentifier . '"'
        );

        if ($this->isBackendUser()) {
            return;
        }

        $event->renderedContent = $this->noticeRenderer->render(
            $formRuntime,
            $this->killSwitch->getMessage($formIdentifier, $this->siteIdentifier($formRuntime))
        );
    }

    /**
     * Whether this runtime is one the switch should stop. Kept in one place so
     * the render side and the two submit-side guards cannot disagree — a form
     * that renders a notice but still mails would be worse than no switch.
     */
    private function blocks(FormRuntime $formRuntime): bool
    {
        if (!$this->isFrontend($formRuntime->getRequest()) || $this->isBackendUser()) {
            return false;
        }

        return $this->killSwitch->isDisabled(
            $formRuntime->getFormDefinition()->getIdentifier(),
            $this->siteIdentifier($formRuntime)
        );
    }

    /**
     * The form editor renders its preview through the same runtime. Without
     * this the editor would show the outage notice instead of the form the
     * editor is trying to fix, which is exactly when someone needs to see it.
     */
    private function isFrontend(ServerRequestInterface $request): bool
    {
        // is_int, not a null check: that is exactly the precondition
        // ApplicationType::fromRequest() throws on, and an exception thrown
        // from here would take down the page the switch is meant to keep
        // serving.
        if (!is_int($request->getAttribute('applicationType'))) {
            return false;
        }

        return ApplicationType::fromRequest($request)->isFrontend();
    }

    /**
     * hasAspect() first: the aspect is put in place by a middleware, so on a
     * request that never ran it (a functional test, a sub-request) reading it
     * straight away throws AspectNotFoundException — and an exception here
     * would take down the very page the switch is supposed to keep serving.
     */
    private function isBackendUser(): bool
    {
        if (!$this->context->hasAspect('backend.user')) {
            return false;
        }

        return $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn', false) === true;
    }

    private function siteIdentifier(FormRuntime $formRuntime): string
    {
        $site = $formRuntime->getRequest()->getAttribute('site');

        return $site instanceof Site ? $site->getIdentifier() : '';
    }

    private function tagPage(ServerRequestInterface $request, string $formIdentifier): void
    {
        $collector = $request->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollector) {
            return;
        }

        $collector->addCacheTags(
            new CacheTag(FormKillSwitch::CACHE_TAG),
            new CacheTag(FormKillSwitch::cacheTagForForm($formIdentifier)),
        );
    }
}
