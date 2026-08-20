<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Form\Service\MailLogRecipientFormatter;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MailLogRecipientFormatterTest extends UnitTestCase
{
    private const KEY_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const KEY_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private function subject(string $encryptionKey = self::KEY_A): MailLogRecipientFormatter
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $encryptionKey;

        return new MailLogRecipientFormatter(new HashService());
    }

    #[Test]
    public function fullModeStoresTheAddressesVerbatim(): void
    {
        [$value, $count] = $this->subject()->format(
            [new Address('erika@example.com'), new Address('max@example.org')],
            'full'
        );

        self::assertSame('erika@example.com, max@example.org', $value);
        self::assertSame(2, $count);
    }

    #[Test]
    public function noneModeStoresNothingButStillCounts(): void
    {
        // The count answers the "recipients option was empty" failure and is
        // never personal data, so it is reported even here.
        [$value, $count] = $this->subject()->format(
            [new Address('erika@example.com'), new Address('max@example.org')],
            'none'
        );

        self::assertSame('', $value);
        self::assertSame(2, $count);
    }

    #[Test]
    public function domainModeDropsTheIdentifyingLocalPart(): void
    {
        [$value, $count] = $this->subject()->format(
            [new Address('Erika.Mustermann@Example.COM')],
            'domain'
        );

        self::assertSame('@example.com', $value);
        self::assertSame(1, $count);
    }

    #[Test]
    public function domainModeCollapsesRepeatedDomains(): void
    {
        // Ten recipients at one provider are one useful fact, not ten.
        [$value, $count] = $this->subject()->format(
            [new Address('a@example.com'), new Address('b@example.com'), new Address('c@other.test')],
            'domain'
        );

        self::assertSame('@example.com, @other.test', $value);
        self::assertSame(3, $count, 'the true recipient count must survive the collapse');
    }

    #[Test]
    public function hashedModeIsStableAndCaseInsensitive(): void
    {
        $subject = $this->subject();

        [$lower] = $subject->format([new Address('erika@example.com')], 'hashed');
        [$mixed] = $subject->format([new Address('Erika@Example.com')], 'hashed');

        self::assertSame($lower, $mixed, 'the same person must hash to the same value');
        self::assertNotSame('', $lower);
        self::assertStringNotContainsStringIgnoringCase('erika', $lower);
    }

    #[Test]
    public function hashedModeIsKeyedByTheEncryptionKey(): void
    {
        // This is the test that proves the value is an HMAC and not a bare
        // sha256: a plain digest of an e-mail address is a reversible
        // identifier, because the address space is enumerable. Only the
        // instance secret makes it pseudonymous.
        [$withKeyA] = $this->subject(self::KEY_A)->format([new Address('erika@example.com')], 'hashed');
        [$withKeyB] = $this->subject(self::KEY_B)->format([new Address('erika@example.com')], 'hashed');

        self::assertNotSame($withKeyA, $withKeyB);
    }

    #[Test]
    public function hashedModeKeepsDistinctRecipientsDistinct(): void
    {
        [$value, $count] = $this->subject()->format(
            [new Address('a@example.com'), new Address('b@example.com')],
            'hashed'
        );

        self::assertCount(2, explode(', ', $value));
        self::assertSame(2, $count);
    }

    #[Test]
    public function anEmptyRecipientListYieldsAnEmptyValueAndZero(): void
    {
        [$value, $count] = $this->subject()->format([], 'full');

        self::assertSame('', $value);
        self::assertSame(0, $count);
    }

    #[Test]
    public function anUnknownModeFallsBackToStoringTheAddress(): void
    {
        // The policy normalises the mode before it ever gets here, so this only
        // documents that the formatter itself does not invent a narrower mode.
        [$value] = $this->subject()->format([new Address('erika@example.com')], 'whatever');

        self::assertSame('erika@example.com', $value);
    }

    #[Test]
    public function aLongRecipientListIsTruncatedToTheColumnWidth(): void
    {
        $addresses = [];
        for ($i = 0; $i < 40; $i++) {
            $addresses[] = new Address(sprintf('recipient-with-a-long-name-%02d@example.com', $i));
        }

        [$value, $count] = $this->subject()->format($addresses, 'full');

        self::assertLessThanOrEqual(255, mb_strlen($value));
        self::assertStringEndsWith('…', $value);
        self::assertSame(40, $count, 'truncating the text must not falsify the count');
    }
}
