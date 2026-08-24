<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Shared scaffolding for the views of the form log module.
 *
 * They all answer questions about the same submissions from different ends —
 * "did the mail go out?", "what did people get wrong on the way?", "did this
 * person consent, and to what?" — so they belong in one module with a switch
 * rather than in separate menu entries. Everything that makes them feel like
 * one module lives here: the view menu, the shortcut context and the title.
 *
 * @internal not part of public TYPO3 Core API
 */
abstract class AbstractFormLogController extends ActionController
{
    protected const PAGINATION_MAX = 20;

    /**
     * Window the form-identifier dropdowns are built from. Wider than the
     * default listing range so a form that stopped producing rows recently is
     * still selectable.
     */
    protected const FILTER_OPTIONS_DAYS = 180;

    /**
     * The module's views, in menu order.
     *
     * @var list<array{controller: string, action: string, label: string, icon: string}>
     */
    protected const VIEWS = [
        [
            'controller' => 'MailLog',
            'action' => 'index',
            'label' => 'mailLog.headline',
            'icon' => 'actions-envelope',
        ],
        [
            'controller' => 'ValidationStats',
            'action' => 'index',
            'label' => 'validationStats.headline',
            'icon' => 'content-widget-chart-bar',
        ],
        [
            'controller' => 'ConsentLog',
            'action' => 'index',
            'label' => 'consentLog.headline',
            'icon' => 'actions-check',
        ],
    ];

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly ComponentFactory $componentFactory,
        protected readonly IconFactory $iconFactory,
    ) {}

    /**
     * @param array<string, mixed> $shortcutArguments
     */
    protected function createModuleTemplate(
        ServerRequestInterface $request,
        string $titleKey,
        array $shortcutArguments = [],
    ): ModuleTemplate {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->addViewMenu($moduleTemplate);

        $moduleTemplate->getDocHeaderComponent()->setShortcutContext(
            'form_log',
            $this->translate($titleKey),
            // Extbase derives the plugin namespace from the module identifier, so
            // it is tx_form_form_log — not the container's identifier, which is
            // what FormManagerController gets wrong.
            ['tx_form_form_log' => $shortcutArguments]
        );

        $moduleTemplate->setModuleClass($this->request->getPluginName() . '_' . $this->request->getControllerName());
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        $moduleTemplate->setTitle($this->translate($titleKey));

        return $moduleTemplate;
    }

    /**
     * Target of the filter forms: the action URI split into a plain path and
     * the query parameters it carried.
     *
     * A GET form does not append to the query string of its action - it
     * replaces it with its own fields. The request token that every backend
     * route URI carries would therefore be gone on submit, and without it
     * RouteDispatcher::assertRequestToken() throws MissingRequestTokenException,
     * whereupon RequestHandler redirects to the login route. For a user who is
     * still logged in that route answers with the backend shell, so the module
     * frame renders the whole backend a second time instead of the filtered
     * list.
     *
     * Splitting rather than naming the token explicitly keeps whatever else the
     * URI builder decides to put in the query (`id`, and anything a future
     * TYPO3 version adds) intact.
     *
     * @return array{uri: string, hiddenFields: array<string, string>}
     */
    protected function buildFilterFormTarget(string $action = 'index'): array
    {
        $uri = (string)$this->uriBuilder->reset()->uriFor($action);

        $hiddenFields = [];
        foreach (explode('&', (string)parse_url($uri, PHP_URL_QUERY)) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $hiddenFields[urldecode($name)] = urldecode($value);
        }

        return [
            'uri' => (string)parse_url($uri, PHP_URL_PATH),
            'hiddenFields' => $hiddenFields,
        ];
    }

    private function addViewMenu(ModuleTemplate $moduleTemplate): void
    {
        $currentController = $this->request->getControllerName();

        $menu = $this->componentFactory->createMenu()
            ->setIdentifier('formLogViews')
            ->setLabel($this->translate('formLog.views'));

        foreach (static::VIEWS as $view) {
            $menu->addMenuItem(
                $this->componentFactory->createMenuItem()
                    ->setTitle($this->translate($view['label']))
                    ->setHref((string)$this->uriBuilder->uriFor($view['action'], [], $view['controller']))
                    ->setActive($currentController === $view['controller'])
            );
        }

        $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    protected function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:form/Resources/Private/Language/Database.xlf:' . $key
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
