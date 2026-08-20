<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Domain\DTO;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Form\Domain\DTO\MailLogPolicy;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

final class MailLogPolicyTest extends TestCase
{
    #[Test]
    public function masterSwitchOffDisablesEverything(): void
    {
        $policy = MailLogPolicy::resolve(['mailLog' => ['enable' => true]], [], false, true);

        self::assertFalse($policy->enabled);
        self::assertFalse($policy->personalData);
    }

    #[Test]
    public function anExplicitFalseOnTheFormWinsOverTheInstanceDefault(): void
    {
        // The escape hatch for a form that must not be logged even on an
        // instance that logs everything else.
        $policy = MailLogPolicy::resolve(['mailLog' => ['enable' => false]], [], true, true);

        self::assertFalse($policy->enabled);
    }

    #[Test]
    public function withoutOptInRowsAreStillWrittenButCarryNoPersonalData(): void
    {
        // This is the case that matters: the form nobody watches is the form
        // nobody opts in, and it is the one that stayed broken for ten days.
        $policy = MailLogPolicy::resolve([], [], true, true);

        self::assertTrue($policy->enabled);
        self::assertFalse($policy->personalData);
        self::assertSame('none', $policy->recipientMode);
        self::assertFalse($policy->logSubject);
        self::assertFalse($policy->logSender);
        self::assertFalse($policy->logReplyTo);
    }

    #[Test]
    public function withoutOptInAndWithoutAllFormsNothingIsWritten(): void
    {
        $policy = MailLogPolicy::resolve([], [], true, false);

        self::assertFalse($policy->enabled);
    }

    #[Test]
    public function optingInUnlocksThePersonalDataColumns(): void
    {
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['enable' => true, 'recipients' => 'full', 'subject' => true]],
            [],
            true,
            false
        );

        self::assertTrue($policy->enabled);
        self::assertTrue($policy->personalData);
        self::assertSame('full', $policy->recipientMode);
        self::assertTrue($policy->logSubject);
    }

    #[Test]
    public function personalDataSettingsAreIgnoredWithoutAnOptIn(): void
    {
        // Configuring recipients: full without enabling the log must not
        // quietly start storing addresses.
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['recipients' => 'full', 'subject' => true, 'sender' => true, 'replyTo' => true]],
            [],
            true,
            true
        );

        self::assertTrue($policy->enabled);
        self::assertSame('none', $policy->recipientMode);
        self::assertFalse($policy->logSubject);
        self::assertFalse($policy->logSender);
        self::assertFalse($policy->logReplyTo);
    }

    #[Test]
    public function theFinisherLevelWinsOverTheFormLevel(): void
    {
        // EmailToReceiver mails our own inbox, EmailToSender mails the visitor —
        // one form-wide setting cannot be right for both.
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['enable' => true, 'recipients' => 'full']],
            ['mailLog' => ['recipients' => 'none']],
            true,
            false
        );

        self::assertSame('none', $policy->recipientMode);
    }

    #[Test]
    public function aFinisherCanSwitchOffLoggingForItselfOnly(): void
    {
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['enable' => true]],
            ['mailLog' => ['enable' => false]],
            true,
            true
        );

        self::assertFalse($policy->enabled);
    }

    #[Test]
    public function theDefaultRecipientModeIsDomain(): void
    {
        $policy = MailLogPolicy::resolve(['mailLog' => ['enable' => true]], [], true, false);

        self::assertSame('domain', $policy->recipientMode);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableRecipientModeProvider(): array
    {
        return [
            'unknown word' => ['everything'],
            'empty string' => [''],
            'not a string' => [true],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('unusableRecipientModeProvider')]
    public function anUnusableRecipientModeFallsBackToTheDefault(mixed $mode): void
    {
        // A typo in the YAML must not silently widen what is stored.
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['enable' => true, 'recipients' => $mode]],
            [],
            true,
            false
        );

        self::assertSame('domain', $policy->recipientMode);
    }

    #[Test]
    public function configurationErrorTextIsStoredEvenWithoutAnOptIn(): void
    {
        // "The option senderAddress must be set" contains no personal data and
        // is the single most useful string this log can hold — it is what let
        // the broken monitoring form diagnose itself.
        $policy = MailLogPolicy::resolve([], [], true, true);

        self::assertTrue($policy->mayStoreErrorMessage(1327060210));
    }

    #[Test]
    public function transportErrorTextNeedsAnOptIn(): void
    {
        // An SMTP rejection quotes the recipient: "550 <john@example.com>: user unknown".
        $withoutOptIn = MailLogPolicy::resolve([], [], true, true);
        self::assertFalse($withoutOptIn->mayStoreErrorMessage(1754047320));

        $withOptIn = MailLogPolicy::resolve(['mailLog' => ['enable' => true]], [], true, false);
        self::assertTrue($withOptIn->mayStoreErrorMessage(1754047320));
    }

    #[Test]
    public function errorDetailFalseSuppressesEveryErrorText(): void
    {
        $policy = MailLogPolicy::resolve(
            ['mailLog' => ['enable' => true, 'errorDetail' => false]],
            [],
            true,
            false
        );

        self::assertFalse($policy->mayStoreErrorMessage(1327060210));
        self::assertFalse($policy->mayStoreErrorMessage(1754047320));
    }

    #[Test]
    public function anExceptionWithoutAForkCodeIsTreatedAsSafe(): void
    {
        // A non-FinisherException carries no transport payload, so its message
        // is judged like a configuration error rather than like a bounce.
        $policy = MailLogPolicy::resolve([], [], true, true);

        self::assertTrue($policy->mayStoreExceptionMessage(new \RuntimeException('boom')));
        self::assertFalse($policy->mayStoreExceptionMessage(new FinisherException('nope', 1754047320)));
    }

    #[Test]
    public function aDisabledPolicyStoresNothingAtAll(): void
    {
        $policy = MailLogPolicy::disabled();

        self::assertFalse($policy->enabled);
        self::assertFalse($policy->personalData);
        self::assertSame('none', $policy->recipientMode);
        self::assertFalse($policy->mayStoreErrorMessage(1327060210));
    }
}
