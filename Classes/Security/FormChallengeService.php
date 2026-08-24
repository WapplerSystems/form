<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Security;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Issues and verifies the challenge/response token of the JavaScript spam
 * shield (see Classes/EventListener/InjectFormChallenge.php for the rendering
 * side and Resources/Public/JavaScript/frontend/challenge.js for the client).
 *
 * How it works
 * ------------
 * On render the server issues a signed *token* and puts an obfuscated form of
 * it — the *challenge* — into the markup. The frontend module reverses the
 * obfuscation and writes the token into a hidden field as the *response*. On
 * submit the server only has to check the token's own signature: a client that
 * never ran the JavaScript submits nothing, and a client that blindly echoes
 * the challenge back submits a string whose signature does not verify, because
 * the obfuscation mangled it.
 *
 * Why it is stateless
 * -------------------
 * Everything needed for verification is inside the token, so nothing has to be
 * kept in the session. That matters because the *initial* render of a form goes
 * through the cacheable `render` action (see ExtensionUtility::configurePlugin()
 * in ext_localconf.php — only `perform` is non-cacheable), i.e. the challenge is
 * baked into the page cache and may be served to many visitors over the cache
 * lifetime. A session-bound or single-use token would break exactly there — the
 * mistake the honeypot avoids by storing its random field name in the session
 * and accepting that it is per-session, not per-request.
 *
 * The consequence is that a token stays valid for as long as its `maxAge`
 * allows, which is why `maxAge` defaults to 0 (no expiry check): a max age
 * shorter than the page cache lifetime would reject legitimate submissions from
 * a cached page. Set it only if the page hosting the form is uncached or has a
 * known, shorter lifetime. The security property this shield provides is
 * "a JavaScript engine ran and transformed the challenge", not freshness.
 *
 * The token is bound to the form identifier, so a challenge harvested from one
 * form cannot be replayed against another.
 */
class FormChallengeService implements SingletonInterface
{
    /**
     * Namespacing secret mixed into the HMAC on top of the instance's
     * encryptionKey, so a token from this feature is not interchangeable with
     * any other HMAC the framework produces.
     */
    private const HMAC_SECRET = 'wapplersystems/form/challenge';

    /**
     * Obfuscation transforms available for the rendered challenge. Each one must
     * have an identical implementation in challenge.js — keep the two in sync.
     */
    public const OBFUSCATION_METHODS = ['rot13reverse', 'rot13', 'reverse', 'base64', 'none'];

    public const DEFAULT_OBFUSCATION_METHOD = 'rot13reverse';

    /**
     * Name of the hidden field the frontend module writes the deobfuscated token
     * into. Rendered outside the form's Extbase argument namespace on purpose:
     * it is not a form element, must not be property-mapped, and must not show
     * up in finisher output or the summary page.
     */
    public const RESPONSE_FIELD = 'tx_form_challenge_response';

    /**
     * Rendered as the response field's initial value, overwritten by the client
     * as soon as it has solved the challenge.
     *
     * Exists so a rejection can name its cause. An empty field means "no
     * JavaScript ran" and a wrong token means "the answer was wrong", but with
     * the field starting out empty the two collapse into one another and the
     * visitor gets the same shrug either way. A client that never executed the
     * script submits this value back verbatim, which is a fact worth telling
     * them about: "your browser did not run our script" is actionable, "could
     * not be verified" is not.
     */
    public const SCRIPT_MISSING_SENTINEL = 'no-javascript';

    /**
     * Name of the hidden field carrying the milliseconds the visitor spent on
     * the currently displayed step, as measured by the frontend module.
     * Consumed by MinimumFillTimeValidator.
     */
    public const FILL_TIME_FIELD = 'tx_form_fill_time';

    public function __construct(
        private readonly HashService $hashService,
    ) {}

    /**
     * Creates a signed token for the given form.
     *
     * @param string $formIdentifier Binds the token to one form definition.
     * @param int|null $issuedAt Unix timestamp; defaults to now. Injectable for tests.
     */
    public function createToken(string $formIdentifier, ?int $issuedAt = null): string
    {
        $payload = json_encode(
            [
                'f' => $formIdentifier,
                't' => $issuedAt ?? time(),
                // A nonce keeps two renders of the same form in the same second
                // from producing the identical challenge string. It carries no
                // meaning for verification.
                'n' => bin2hex(random_bytes(6)),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $encodedPayload = $this->base64UrlEncode($payload);

        return $encodedPayload . '.' . $this->sign($encodedPayload);
    }

    /**
     * Issue time of a token that verifies for this form, or NULL.
     *
     * Same check as verifyToken() without the age limit: the caller wants to
     * know when the visitor first saw the form, not whether that was recently
     * enough.
     */
    public function readIssuedAt(string $token, string $formIdentifier): ?int
    {
        return $this->verifyToken($token, $formIdentifier, 0);
    }

    /**
     * Verifies a token submitted as the challenge response.
     *
     * @param string $token The value the client submitted.
     * @param string $formIdentifier The form the submission belongs to.
     * @param int $maxAge Maximum token age in seconds; 0 disables the age check.
     * @param int|null $now Unix timestamp to compare against; defaults to now. Injectable for tests.
     * @return int|null The token's issue time on success, NULL when the token is
     *                 malformed, forged, meant for another form or expired.
     */
    public function verifyToken(string $token, string $formIdentifier, int $maxAge = 0, ?int $now = null): ?int
    {
        $separatorPosition = strrpos($token, '.');
        if ($separatorPosition === false || $separatorPosition === 0) {
            return null;
        }
        $encodedPayload = substr($token, 0, $separatorPosition);
        $signature = substr($token, $separatorPosition + 1);

        if (!hash_equals($this->sign($encodedPayload), $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || !isset($payload['f'], $payload['t'])) {
            return null;
        }
        if (!is_string($payload['f']) || !hash_equals($formIdentifier, $payload['f'])) {
            return null;
        }
        if (!is_int($payload['t'])) {
            return null;
        }

        $issuedAt = $payload['t'];
        $now ??= time();
        if ($maxAge > 0 && $now - $issuedAt > $maxAge) {
            return null;
        }

        return $issuedAt;
    }

    /**
     * Turns a token into the challenge string that goes into the markup.
     *
     * The obfuscation is deliberately trivial cryptography-wise — it exists so
     * that a bot which simply copies every value it finds in the markup into
     * every field it finds submits something that fails the signature check.
     * Anything stronger would be pointless: the reversing algorithm has to ship
     * to the client, so it is always public.
     */
    public function obfuscate(string $token, string $method = self::DEFAULT_OBFUSCATION_METHOD): string
    {
        return match ($this->normalizeMethod($method)) {
            'rot13' => str_rot13($token),
            'reverse' => strrev($token),
            'rot13reverse' => strrev(str_rot13($token)),
            'base64' => base64_encode($token),
            default => $token,
        };
    }

    /**
     * Inverse of obfuscate(). Only used by tests and by callers that want to
     * verify parity with the JavaScript implementation — the frontend does this
     * transform itself.
     */
    public function deobfuscate(string $challenge, string $method = self::DEFAULT_OBFUSCATION_METHOD): string
    {
        return match ($this->normalizeMethod($method)) {
            'rot13' => str_rot13($challenge),
            'reverse' => strrev($challenge),
            'rot13reverse' => str_rot13(strrev($challenge)),
            'base64' => (string)base64_decode($challenge, true),
            default => $challenge,
        };
    }

    /**
     * Unknown method names fall back to the default rather than throwing: a typo
     * in a form's YAML should not take the form down, and the fallback is the
     * stronger of the transforms.
     */
    public function normalizeMethod(string $method): string
    {
        return in_array($method, self::OBFUSCATION_METHODS, true)
            ? $method
            : self::DEFAULT_OBFUSCATION_METHOD;
    }

    private function sign(string $encodedPayload): string
    {
        return $this->hashService->hmac($encodedPayload, self::HMAC_SECRET);
    }

    /**
     * Base64url without padding — keeps the token free of characters that would
     * need escaping in HTML attributes or JSON, and keeps `.` available as the
     * payload/signature separator.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string)base64_decode(strtr($value, '-_', '+/'), true);
    }
}
