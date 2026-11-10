<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Form\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * release/v13 only: stands in for v14's virtual `~labels/<domain>` modules.
 *
 * The editor, manager and wizard scripts are shared with release/v14 and
 * import their labels as `~labels/<domain>`. TYPO3 13.4 cannot resolve those
 * specifiers, so the import map maps them to small shim modules
 * (Resources/Public/JavaScript/compat/labels/), which fetch the labels of
 * their domain here. Only the domains listed below are served.
 *
 * @internal
 */
#[AsController]
final class JavaScriptLabelsController
{
    private const DOMAINS = [
        'form.form_manager_javascript' => 'EXT:form/Resources/Private/Language/locallang_formManager_javascript.xlf',
        'form.form_editor_javascript' => 'EXT:form/Resources/Private/Language/locallang_form_editor_javascript.xlf',
        'form.form_editor_fail_safe_error_handling_javascript' => 'EXT:form/Resources/Private/Language/locallang_formEditor_failSafeErrorHandling_javascript.xlf',
        'form.relative_date_editor' => 'EXT:form/Resources/Private/Language/locallang_relative_date_editor.xlf',
        'backend.wizards.general' => 'EXT:form/Resources/Private/Language/Compat/backend_wizards_general.xlf',
    ];

    public function labelsAction(ServerRequestInterface $request): ResponseInterface
    {
        $domain = (string)($request->getQueryParams()['domain'] ?? '');
        $file = self::DOMAINS[$domain] ?? null;
        if ($file === null) {
            return new JsonResponse(['error' => 'Unknown label domain'], 404);
        }
        $labels = $this->getLanguageService()->getLabelsFromResource($file);
        return new JsonResponse($labels, 200, [
            // Labels follow the backend user's language; keep them out of shared caches.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
