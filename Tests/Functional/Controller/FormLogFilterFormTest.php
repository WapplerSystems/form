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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Backend\Routing\Route as BackendRoute;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The filter of both form-log views is a GET form, and a GET form replaces the
 * query string of its action URI instead of adding to it. Everything the URI
 * carried - the request token above all - therefore has to be re-emitted as a
 * hidden field, or the submission reaches the module route without a token,
 * RouteDispatcher throws MissingRequestTokenException and the RequestHandler
 * answers with the login route: inside the module frame that renders the whole
 * backend a second time instead of the filtered list.
 */
final class FormLogFilterFormTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'form',
    ];

    /**
     * @return array<string, array{routeIdentifier: string, path: string}>
     */
    public static function formLogViewsDataProvider(): array
    {
        return [
            'mail log' => [
                'routeIdentifier' => 'form_log.MailLog_index',
                'path' => '/module/form/log/MailLog/index',
            ],
            'validation statistics' => [
                'routeIdentifier' => 'form_log.ValidationStats_index',
                'path' => '/module/form/log/ValidationStats/index',
            ],
        ];
    }

    #[DataProvider('formLogViewsDataProvider')]
    #[Test]
    public function filterFormSubmitsToTheModulePathAndCarriesTheTokenAsHiddenField(
        string $routeIdentifier,
        string $path
    ): void {
        $form = $this->extractFilterForm($this->renderModule($routeIdentifier));

        // The path is matched without its prefix and the query string is
        // forbidden by the character class rather than by a second assertion:
        // a query string on the action is not merely redundant, the browser
        // drops it, so anything only living there is lost on submit.
        self::assertMatchesRegularExpression(
            '#^<form method="get" action="[^"?]*' . preg_quote($path, '#') . '"#',
            $form
        );
        self::assertStringContainsString('<input type="hidden" name="token"', $form);
    }

    /**
     * The assertions run against the form element alone: asserting on the whole
     * module response buries every failure message under a full backend
     * document. An empty string means no GET form was rendered at all, which
     * fails the assertions with a readable message.
     */
    private function extractFilterForm(string $body): string
    {
        preg_match('#<form method="get".*?</form>#s', $body, $matches);
        return $matches[0] ?? '';
    }

    private function renderModule(string $routeIdentifier): string
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        $route = $this->createBackendRoute(
            $this->get(Router::class)->getRoute($routeIdentifier),
            $routeIdentifier
        );
        $serverRequest = (new ServerRequest('https://example.com/typo3' . $route->getPath(), 'GET'))
            ->withAttribute('route', $route)
            ->withAttribute('module', $route->getOption('module'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        // Rendering the module publishes assets through core's system resource
        // publisher, which reads "normalizedParams" off the request.
        $serverRequest = $serverRequest->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($serverRequest)
        );
        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        return (string)$this->get(Bootstrap::class)->handleBackendRequest($serverRequest)->getBody();
    }

    /**
     * @see FormManagerControllerTest::createBackendRouteFromSymfonyRoute()
     */
    private function createBackendRoute(SymfonyRoute $symfonyRoute, string $routeIdentifier): BackendRoute
    {
        $options = $symfonyRoute->getOptions();
        $options['_identifier'] = $routeIdentifier;
        unset($options['methods']);
        return new BackendRoute($symfonyRoute->getPath(), $options);
    }
}
