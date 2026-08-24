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
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
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

        // A re-render after a rejected submission must not restart the clock.
        // Both halves of the fill-time measurement otherwise reset to zero, and
        // a visitor who spent a minute on the form and then fixes a typo in
        // three seconds is told they were too fast - measured on the live site
        // this hit ten separate people on one form. Carrying the original
        // render time and the already measured milliseconds forward keeps the
        // total honest without softening the check: a bot that submits twice in
        // a row still shows a token age near zero.
        $previous = $this->readPreviousMeasurement($event->formRuntime, $formDefinition->getIdentifier());

        if ($challengeOptions !== null) {
            $method = $this->challengeService->normalizeMethod(
                (string)($challengeOptions['obfuscationMethod'] ?? FormChallengeService::DEFAULT_OBFUSCATION_METHOD)
            );
            $token = $this->challengeService->createToken(
                $formDefinition->getIdentifier(),
                $previous['issuedAt']
            );

            $island['challenge'] = $this->challengeService->obfuscate($token, $method);
            $island['method'] = $method;
            // Seconds in YAML — that is the unit an integrator thinks in —
            // milliseconds in the island, which is the unit setTimeout wants.
            $island['delay'] = (int)round(max(0.0, (float)($challengeOptions['delay'] ?? self::DEFAULT_DELAY)) * 1000);
            $island['fields']['response'] = FormChallengeService::RESPONSE_FIELD;

            $hiddenFields .= $this->hiddenField(
                FormChallengeService::RESPONSE_FIELD,
                FormChallengeService::SCRIPT_MISSING_SENTINEL
            );
        }

        if ($needsFillTime) {
            $island['fields']['time'] = FormChallengeService::FILL_TIME_FIELD;
            // Milliseconds the visitor already spent on this form before the
            // rejected submission; the client adds its own measurement on top.
            $island['elapsed'] = $previous['elapsedMilliseconds'];
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
     * What the visitor already invested before a rejected submission: the issue
     * time of the token they sent back, and the milliseconds their browser had
     * measured.
     *
     * Both are taken only from a token whose signature verifies for this very
     * form, so neither can be inflated by a client: the issue time is the
     * server's own, and the reported milliseconds are capped by how long that
     * token has actually existed.
     *
     * @return array{issuedAt: int|null, elapsedMilliseconds: int}
     */
    private function readPreviousMeasurement(FormRuntime $formRuntime, string $formIdentifier): array
    {
        $none = ['issuedAt' => null, 'elapsedMilliseconds' => 0];

        $parsedBody = $formRuntime->getRequest()->getParsedBody();
        if (!is_array($parsedBody)) {
            return $none;
        }

        $response = $parsedBody[FormChallengeService::RESPONSE_FIELD] ?? null;
        if (!is_string($response) || $response === '') {
            return $none;
        }

        $issuedAt = $this->challengeService->readIssuedAt($response, $formIdentifier);
        if ($issuedAt === null) {
            return $none;
        }

        $reported = $parsedBody[FormChallengeService::FILL_TIME_FIELD] ?? null;
        $reported = (is_string($reported) || is_int($reported)) && ctype_digit((string)$reported)
            ? (int)$reported
            : 0;

        // The form cannot have been on screen longer than its token has
        // existed, so that is the ceiling - a client reporting more is either
        // broken or trying it on.
        $ceiling = max(0, time() - $issuedAt) * 1000;

        return [
            'issuedAt' => $issuedAt,
            'elapsedMilliseconds' => max(0, min($reported, $ceiling)),
        ];
    }

    /**
     * `autocomplete="off"` matters here: browsers otherwise restore the previous
     * value of a same-named hidden field on a back-navigation, which would hand
     * a stale (possibly expired) token or a stale elapsed time to the next
     * submission instead of the freshly issued one.
     */
    private function hiddenField(string $name, string $value = ''): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES)
            . '" value="' . htmlspecialchars($value, ENT_QUOTES) . '" autocomplete="off" />';
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
