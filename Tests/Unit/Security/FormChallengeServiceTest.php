<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Form\Security\FormChallengeService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormChallengeServiceTest extends UnitTestCase
{
    private FormChallengeService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // HashService derives its key from the instance encryptionKey.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->subject = new FormChallengeService(new HashService());
    }

    #[Test]
    public function issuedTokenVerifiesAndReturnsItsIssueTime(): void
    {
        $token = $this->subject->createToken('ContactForm', 1700000000);

        self::assertSame(1700000000, $this->subject->verifyToken($token, 'ContactForm'));
    }

    #[Test]
    public function twoTokensForTheSameFormAndSecondDiffer(): void
    {
        // The nonce is what keeps a cached page from being recognisable by a
        // constant challenge string.
        self::assertNotSame(
            $this->subject->createToken('ContactForm', 1700000000),
            $this->subject->createToken('ContactForm', 1700000000)
        );
    }

    #[Test]
    public function tokenOfAnotherFormIsRejected(): void
    {
        $token = $this->subject->createToken('NewsletterForm', 1700000000);

        self::assertNull($this->subject->verifyToken($token, 'ContactForm'));
    }

    #[Test]
    public function tamperedPayloadIsRejected(): void
    {
        $token = $this->subject->createToken('ContactForm', 1700000000);
        [, $signature] = explode('.', $token, 2);

        // Re-encode the payload with a later issue time — this is the attack the
        // signature exists for: claiming the form was rendered long ago so the
        // fill-time check passes.
        $forgedPayload = rtrim(strtr(base64_encode(
            (string)json_encode(['f' => 'ContactForm', 't' => 1700009999, 'n' => 'deadbeef'])
        ), '+/', '-_'), '=');

        self::assertNull($this->subject->verifyToken($forgedPayload . '.' . $signature, 'ContactForm'));
    }

    #[Test]
    public function expiredTokenIsRejectedOnlyWhenAMaxAgeIsSet(): void
    {
        $token = $this->subject->createToken('ContactForm', 1700000000);
        $now = 1700000000 + 3600;

        self::assertNull($this->subject->verifyToken($token, 'ContactForm', 900, $now));
        // maxAge 0 is the default, because the challenge is baked into the page
        // cache and would otherwise expire while the cache entry is still served.
        self::assertSame(1700000000, $this->subject->verifyToken($token, 'ContactForm', 0, $now));
    }

    #[Test]
    public function tokenWithinMaxAgeIsAccepted(): void
    {
        $token = $this->subject->createToken('ContactForm', 1700000000);

        self::assertSame(
            1700000000,
            $this->subject->verifyToken($token, 'ContactForm', 900, 1700000000 + 899)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTokenProvider(): array
    {
        return [
            'empty' => [''],
            'no separator' => ['deadbeef'],
            'separator only' => ['.'],
            'empty payload' => ['.deadbeef'],
            'garbage signature' => ['eyJmIjoiQ29udGFjdEZvcm0ifQ.notasignature'],
        ];
    }

    #[Test]
    #[DataProvider('malformedTokenProvider')]
    public function malformedTokenIsRejected(string $token): void
    {
        self::assertNull($this->subject->verifyToken($token, 'ContactForm'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function obfuscationMethodProvider(): array
    {
        return [
            'rot13reverse' => ['rot13reverse'],
            'rot13' => ['rot13'],
            'reverse' => ['reverse'],
            'base64' => ['base64'],
            'none' => ['none'],
            'unknown falls back to the default' => ['nonsense'],
        ];
    }

    #[Test]
    #[DataProvider('obfuscationMethodProvider')]
    public function obfuscationRoundTripsAndTheChallengeIsNotTheToken(string $method): void
    {
        $token = $this->subject->createToken('ContactForm', 1700000000);
        $challenge = $this->subject->obfuscate($token, $method);

        self::assertSame($token, $this->subject->deobfuscate($challenge, $method));

        if ($method !== 'none') {
            // The point of the obfuscation: a bot echoing the challenge back
            // submits something that fails verification.
            self::assertNotSame($token, $challenge);
            self::assertNull($this->subject->verifyToken($challenge, 'ContactForm'));
        }
    }

    /**
     * Pins the exact transforms. challenge.js implements the same five and has no
     * way to be covered from PHPUnit, so these expectations are the contract
     * between the two files — if one side changes, this test is what fails.
     *
     * @return array<string, array{string, string}>
     */
    public static function pinnedObfuscationProvider(): array
    {
        return [
            'rot13reverse' => ['rot13reverse', 'MZ-NoN.pon'],
            'rot13' => ['rot13', 'nop.NoN-ZM'],
            'reverse' => ['reverse', 'ZM-AbA.cba'],
            'base64' => ['base64', 'YWJjLkFiQS1NWg=='],
            'none' => ['none', 'abc.AbA-MZ'],
        ];
    }

    #[Test]
    #[DataProvider('pinnedObfuscationProvider')]
    public function obfuscationMatchesTheJavaScriptImplementation(string $method, string $expected): void
    {
        // Covers both letter cases, the `.` separator and the base64url `-`,
        // none of which ROT13 may touch.
        self::assertSame($expected, $this->subject->obfuscate('abc.AbA-MZ', $method));
    }

    #[Test]
    public function normalizeMethodFallsBackToTheDefault(): void
    {
        self::assertSame('rot13', $this->subject->normalizeMethod('rot13'));
        self::assertSame(
            FormChallengeService::DEFAULT_OBFUSCATION_METHOD,
            $this->subject->normalizeMethod('does-not-exist')
        );
    }
}
