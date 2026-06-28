<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\PasswordPolicy\Validator\CorePasswordValidator;

/**
 * Lightweight JSON endpoint that exposes the frontend password policy
 * configuration so client-side JavaScript can mirror the same rules in
 * a live "is your password strong enough yet?" indicator under the
 * form's password input.
 *
 * Path: /_form/password-policy/
 *
 * Response:
 *   {
 *     "policy": "default",
 *     "rules": [
 *       {"id": "minimumLength",            "label": "…", "value": 8},
 *       {"id": "upperCaseCharacterRequired","label": "…"},
 *       {"id": "lowerCaseCharacterRequired","label": "…"},
 *       {"id": "digitCharacterRequired",    "label": "…"},
 *       {"id": "specialCharacterRequired",  "label": "…"}
 *     ]
 *   }
 *
 * Only the rules that the configured CorePasswordValidator actually
 * enforces appear in the response; a policy that disables
 * specialCharacterRequired simply won't have a "specialCharacterRequired"
 * rule, so the indicator stays in lockstep with the validator.
 *
 * Ported from wapplersystems/form_extended (path renamed from
 * /_form_extended/password-policy to /_form/password-policy/ since this
 * is now part of EXT:form via the fork). Use case: form-plugin password
 * fields where the editor wants live policy feedback.
 *
 * Conceptually this endpoint would also fit `wapplersystems/fe-registration`
 * (password policies are an FE-user concern); the fork carries it for
 * convenience when forms are the only consumer.
 */
final class PasswordPolicyEndpoint implements MiddlewareInterface
{
    private const PATH = '/_form/password-policy/';

    public function __construct(
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== self::PATH) {
            return $handler->handle($request);
        }

        $policyName = (string)($GLOBALS['TYPO3_CONF_VARS']['FE']['passwordPolicy'] ?? 'default');
        $policies = $GLOBALS['TYPO3_CONF_VARS']['SYS']['passwordPolicies'] ?? [];
        $validators = $policies[$policyName]['validators'] ?? [];
        $coreOptions = $validators[CorePasswordValidator::class]['options'] ?? null;

        if (!is_array($coreOptions)) {
            return new JsonResponse(['policy' => $policyName, 'rules' => []]);
        }

        $site = $request->getAttribute('site');
        $language = $site !== null && method_exists($site, 'getDefaultLanguage')
            ? $site->getDefaultLanguage()
            : null;
        $ls = $language !== null
            ? $this->languageServiceFactory->createFromSiteLanguage($language)
            : $this->languageServiceFactory->create('default');

        $labelOf = static fn (string $key): string => $ls->sL(
            'LLL:EXT:core/Resources/Private/Language/locallang_password_policy.xlf:requirement.' . $key,
        ) ?: $ls->sL(
            'LLL:EXT:core/Resources/Private/Language/locallang_password_policy.xlf:error.' . $key,
        ) ?: $key;

        $rules = [];
        $minLength = (int)($coreOptions['minimumLength'] ?? 0);
        if ($minLength > 0) {
            $rules[] = [
                'id' => 'minimumLength',
                'value' => $minLength,
                'label' => sprintf($labelOf('minimumLength') ?: 'At least %d characters', $minLength),
            ];
        }
        foreach ([
            'upperCaseCharacterRequired',
            'lowerCaseCharacterRequired',
            'digitCharacterRequired',
            'specialCharacterRequired',
        ] as $flag) {
            if (!empty($coreOptions[$flag])) {
                $rules[] = ['id' => $flag, 'label' => $labelOf($flag)];
            }
        }

        return new JsonResponse([
            'policy' => $policyName,
            'rules' => $rules,
        ]);
    }
}
