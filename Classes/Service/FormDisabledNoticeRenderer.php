<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Produces the markup a visitor sees in place of a switched-off form.
 *
 * Through the form's own templateRootPaths rather than a string built in PHP,
 * so a site package overrides the notice exactly the way it overrides every
 * other form template — `templateRootPaths.10` and a `FormDisabled.fluid.html`.
 * A hardcoded <div> would have been three lines and unoverridable, and the one
 * thing an operator wants to change under an incident is the wording and the
 * markup around it.
 *
 * No layout: `layoutRootPaths.0` of this extension points at a directory that
 * does not exist, so the template has to be self-contained.
 */
final class FormDisabledNoticeRenderer
{
    private const TEMPLATE_NAME = 'FormDisabled';
    private const DEFAULT_MESSAGE_LABEL = 'LLL:EXT:form/Resources/Private/Language/locallang.xlf:formKillSwitch.defaultMessage';
    private const FALLBACK_TEMPLATE_ROOT_PATH = 'EXT:form/Resources/Private/Frontend/Templates/';
    private const FALLBACK_PARTIAL_ROOT_PATH = 'EXT:form/Resources/Private/Frontend/Partials/';

    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @param string $message custom wording, empty to use the localized default
     */
    public function render(FormRuntime $formRuntime, string $message): string
    {
        $renderingOptions = $formRuntime->getRenderingOptions();

        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: $this->paths($renderingOptions['templateRootPaths'] ?? null, self::FALLBACK_TEMPLATE_ROOT_PATH),
            partialRootPaths: $this->paths($renderingOptions['partialRootPaths'] ?? null, self::FALLBACK_PARTIAL_ROOT_PATH),
            request: $formRuntime->getRequest(),
        );

        $view = $this->viewFactory->create($viewFactoryData);
        $view->assignMultiple([
            'form' => $formRuntime,
            'formIdentifier' => $formRuntime->getFormDefinition()->getIdentifier(),
            'message' => $this->resolveMessage($formRuntime, $message),
        ]);

        return $view->render(self::TEMPLATE_NAME);
    }

    /**
     * The wording actually shown. A custom text from the backend module or the
     * CLI wins; otherwise the XLF default, translated for the site language.
     * Public because the submit-side guard needs the same string for the
     * validation error it attaches, and the two must not drift apart.
     *
     * The trade-off is stated rather than hidden: a custom text is one string
     * for all languages, because it is typed during an incident and not
     * translated.
     */
    public function resolveMessage(FormRuntime $formRuntime, string $message): string
    {
        if (trim($message) !== '') {
            return $message;
        }

        $siteLanguage = $formRuntime->getCurrentSiteLanguage();
        $languageService = $siteLanguage !== null
            ? $this->languageServiceFactory->createFromSiteLanguage($siteLanguage)
            : $this->languageServiceFactory->create('default');

        return $languageService->sL(self::DEFAULT_MESSAGE_LABEL) ?: '';
    }

    /**
     * @return array<int|string, string>
     */
    private function paths(mixed $configured, string $fallback): array
    {
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [$fallback];
    }
}
