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

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Form\Event\AfterFormRenderedEvent;
use TYPO3\CMS\Form\Security\FormChallengeService;
use TYPO3\CMS\Form\Validation\ChallengeValidator;
use TYPO3\CMS\Form\Validation\FormLevelValidators;
use TYPO3\CMS\Form\Validation\MinimumFillTimeValidator;

/**
 * Rendering side of the JavaScript spam shield.
 *
 * Injects — into the rendered <form> — a JSON island describing what the client
 * has to do, the hidden fields it writes its results into, and the frontend
 * module that does the work. Nothing is emitted for forms that use neither
 * feature, so forms and sites that do not opt in are byte-for-byte unchanged.
 *
 * Two independent features share this one island and one module, because both
 * need the same thing (a small script running inside the form) and shipping two
 * scripts for it would double the request count for no gain:
 *
 *  1. Challenge/response — switched on by putting a `Challenge` validator on the
 *     form. Emits the obfuscated challenge plus the hidden response field, and
 *     reads `delay` and `obfuscationMethod` off that validator's own options, so
 *     the whole feature is configured in one place. Verified by
 *     ChallengeValidator itself, dispatched by RunFormLevelValidators.
 *
 *  2. Fill-time measurement — switched on by putting a `MinimumFillTime`
 *     validator on the form. Emits the hidden field the module writes the
 *     elapsed milliseconds into; verified by MinimumFillTimeValidator.
 *
 * The island is `type="application/json"`, i.e. data and not an executable
 * inline script, so it needs no CSP nonce — same approach as
 * InjectFrontendConditions.
 */
#[AsEventListener('wapplersystems-form/inject-form-challenge')]
final class InjectFormChallenge
{
    /**
     * Seconds the client waits before writing the response. Mirrors
     * form_crshield's `crJavaScriptDelay` default.
     */
    private const DEFAULT_DELAY = 3.0;

    /**
     * Key of the challenge validator in the prototype's validatorsDefinition,
     * needed to resolve options for the legacy formLevelValidators spelling.
     */
    private const CHALLENGE_VALIDATOR_IDENTIFIER = 'Challenge';

    public function __construct(
        private readonly FormChallengeService $challengeService,
        private readonly AssetCollector $assetCollector,
    ) {}

    public function __invoke(AfterFormRenderedEvent $event): void
    {
        $formDefinition = $event->formRuntime->getFormDefinition();
        $challengeOptions = FormLevelValidators::findOptions(
            $formDefinition,
            ChallengeValidator::class,
            self::CHALLENGE_VALIDATOR_IDENTIFIER,
        );
        $needsFillTime = FormLevelValidators::has($formDefinition, MinimumFillTimeValidator::class);

        if ($challengeOptions === null && !$needsFillTime) {
            return;
        }

        $island = ['fields' => []];
        $hiddenFields = '';

        if ($challengeOptions !== null) {
            $method = $this->challengeService->normalizeMethod(
                (string)($challengeOptions['obfuscationMethod'] ?? FormChallengeService::DEFAULT_OBFUSCATION_METHOD)
            );
            $token = $this->challengeService->createToken($formDefinition->getIdentifier());

            $island['challenge'] = $this->challengeService->obfuscate($token, $method);
            $island['method'] = $method;
            // Seconds in YAML — that is the unit an integrator thinks in —
            // milliseconds in the island, which is the unit setTimeout wants.
            $island['delay'] = (int)round(max(0.0, (float)($challengeOptions['delay'] ?? self::DEFAULT_DELAY)) * 1000);
            $island['fields']['response'] = FormChallengeService::RESPONSE_FIELD;

            $hiddenFields .= $this->hiddenField(FormChallengeService::RESPONSE_FIELD);
        }

        if ($needsFillTime) {
            $island['fields']['time'] = FormChallengeService::FILL_TIME_FIELD;
            $hiddenFields .= $this->hiddenField(FormChallengeService::FILL_TIME_FIELD);
        }

        $json = json_encode(
            $island,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $event->renderedContent = $this->insertIntoForm(
            $event->renderedContent,
            '<script type="application/json" data-form-challenge="1">' . $json . '</script>' . $hiddenFields
        );

        $this->assetCollector->addJavaScript(
            'wapplersystems-form-challenge',
            'EXT:form/Resources/Public/JavaScript/frontend/challenge.js'
        );
    }

    /**
     * `autocomplete="off"` matters here: browsers otherwise restore the previous
     * value of a same-named hidden field on a back-navigation, which would hand
     * a stale (possibly expired) token or a stale elapsed time to the next
     * submission instead of the freshly issued one.
     */
    private function hiddenField(string $name): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES) . '" value="" autocomplete="off" />';
    }

    /**
     * Insert right after the opening <form …> tag so the frontend module can
     * scope everything via island.closest('form') and so the hidden fields are
     * part of the submitted form. Falls back to appending.
     */
    private function insertIntoForm(string $content, string $markup): string
    {
        $position = stripos($content, '<form');
        if ($position === false) {
            return $content . $markup;
        }
        $tagEnd = strpos($content, '>', $position);
        if ($tagEnd === false) {
            return $content . $markup;
        }
        return substr($content, 0, $tagEnd + 1) . $markup . substr($content, $tagEnd + 1);
    }
}
