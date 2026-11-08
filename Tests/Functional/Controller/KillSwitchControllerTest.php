<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Routing\Route as BackendRoute;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the kill-switch view of the form log module.
 *
 * This is here because the view is the interface people reach for while
 * something is on fire, and a Fluid error in it would only ever surface at
 * exactly that moment. The assertions are deliberately shallow — it renders,
 * it posts to its own action, and it reflects the current state — since
 * anything finer would break on every wording change.
 */
final class KillSwitchControllerTest extends FunctionalTestCase
{
    private const ROUTE = 'form_log.KillSwitch_index';

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    #[Test]
    public function theViewRenders(): void
    {
        $body = $this->renderModule();

        self::assertStringContainsString('Block forms', $body);
        self::assertStringContainsString('Automatic shutdown', $body);
    }

    /**
     * POST, not GET: a state change must not sit behind a URL a prefetcher or a
     * bookmark can replay, and the action URI's query string (the request token
     * above all) is re-emitted as hidden fields because a form replaces the
     * query string of its action rather than adding to it.
     */
    #[Test]
    public function theSwitchesPostAndCarryTheTokenAsHiddenField(): void
    {
        $body = $this->renderModule();

        preg_match('#<form method="post".*?</form>#s', $body, $matches);
        $form = $matches[0] ?? '';

        self::assertMatchesRegularExpression(
            '#^<form method="post" action="[^"?]*/module/form/log/KillSwitch/update"#',
            $form
        );
        self::assertStringContainsString('<input type="hidden" name="token"', $form);
    }

    #[Test]
    public function aBlockedFormIsShownAsBlocked(): void
    {
        $this->get(FormKillSwitch::class)->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'test');

        $body = $this->renderModule();

        self::assertStringContainsString('contact', $body);
        self::assertStringContainsString('Blocked', $body);
    }

    #[Test]
    public function theGlobalBlockIsAnnouncedAtTheTop(): void
    {
        $this->get(FormKillSwitch::class)->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'test');

        self::assertStringContainsString(
            'Every form of this installation is blocked.',
            $this->renderModule()
        );
    }

    private function renderModule(): string
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        $symfonyRoute = $this->get(Router::class)->getRoute(self::ROUTE);
        $options = $symfonyRoute->getOptions();
        $options['_identifier'] = self::ROUTE;
        unset($options['methods']);
        $route = new BackendRoute($symfonyRoute->getPath(), $options);

        $serverRequest = (new ServerRequest('https://example.com/typo3' . $route->getPath(), 'GET'))
            ->withAttribute('route', $route)
            ->withAttribute('module', $route->getOption('module'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $serverRequest = $serverRequest->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($serverRequest)
        );
        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        return (string)$this->get(Bootstrap::class)->handleBackendRequest($serverRequest)->getBody();
    }
}
