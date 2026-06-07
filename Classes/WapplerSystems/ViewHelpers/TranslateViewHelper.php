<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Translate a label using the form-aware TranslationService (with its
 * form-element-overlay logic) rather than Fluid's generic f:translate
 * which hits LocalizationUtility directly.
 *
 * Mostly useful inside templates that render form content where you
 * want consistent form-specific translation behaviour (LLL:EXT:form
 * key fallbacks, form element overlay keys). Outside of form templates
 * use Fluid's f:translate instead.
 *
 * Fluid usage:
 *   {namespace formvh=TYPO3\CMS\Form\WapplerSystems\ViewHelpers}
 *   <formvh:translate key="LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:my.label" />
 *
 * Ported from wapplersystems/form_extended (Phase 5c of the migration).
 * Dead `assertArgumentTypes()` method from the original was dropped.
 */
final class TranslateViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly TranslationService $translationService,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Translation key');
        $this->registerArgument('id', 'string', 'Translation ID. Same as key.');
        $this->registerArgument('default', 'string', 'If the locallang key cannot be found this value is used. Falls back to child nodes when not set.');
        $this->registerArgument('arguments', 'array', 'Arguments to be replaced in the resulting string (vsprintf-style)');
        $this->registerArgument('extensionName', 'string', 'UpperCamelCased extension key (e.g. BlogExample)');
    }

    public function render(): array|string
    {
        $id = (string)($this->arguments['id'] ?? $this->arguments['key'] ?? '');
        if ($id === '') {
            throw new Exception('Either "key" or "id" must be set on formvh:translate', 1351584844);
        }

        $default = (string)($this->arguments['default'] ?? $this->renderChildren() ?? '');
        $extensionName = $this->arguments['extensionName'];
        $translateArguments = $this->arguments['arguments'];

        if (empty($extensionName)) {
            $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
                ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
                : null;
            if ($request instanceof ExtbaseRequestInterface) {
                $extensionName = $request->getControllerExtensionName();
            } elseif (str_starts_with($id, 'LLL:EXT:')) {
                $extensionName = substr($id, 8, strpos($id, '/', 8) - 8);
            } elseif ($default !== '') {
                return self::interpolateDefault($default, $translateArguments);
            } else {
                throw new \RuntimeException(
                    'formvh:translate in a non-extbase context needs the "extensionName" attribute to resolve key="'
                    . $id . '" without a full path. Either pass "extensionName", use a full "LLL:EXT:..." key,'
                    . ' or provide a default value.',
                    1639828178,
                );
            }
        }

        return $this->translationService->translate($id, $translateArguments);
    }

    /**
     * @param array<int|string, mixed>|null $arguments
     */
    private static function interpolateDefault(string $default, ?array $arguments): string
    {
        if ($arguments !== null && $arguments !== []) {
            return vsprintf($default, $arguments);
        }
        return $default;
    }
}
