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

use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * Turns a list of mail addresses into the string the mail log stores, according
 * to the configured privacy mode.
 *
 * Pure apart from the injected HashService, so the whole privacy surface of the
 * feature is unit-testable without a database or a request.
 */
readonly class MailLogRecipientFormatter
{
    /**
     * Column width of tx_form_mail_log.recipients.
     */
    private const MAX_LENGTH = 255;

    /**
     * Namespacing secret so a recipient hash cannot be compared against an HMAC
     * this extension produces for anything else.
     */
    private const HMAC_SECRET = 'wapplersystems/form/mail-log-recipient';

    /**
     * Half a SHA-256 is ample for "is this the same address as that one" and
     * keeps the column readable. Truncation does not weaken the construction —
     * the secret is what protects the address, not the digest length.
     */
    private const HASH_LENGTH = 16;

    public function __construct(
        private HashService $hashService,
    ) {}

    /**
     * @param list<Address> $addresses
     * @return array{0: string, 1: int} The stored string and the true count
     */
    public function format(array $addresses, string $mode): array
    {
        // The count is never personal data, and it is what answers the
        // "recipients option was empty" failure — so it is reported truthfully
        // even when the addresses themselves are not stored at all.
        $count = count($addresses);

        if ($mode === 'none' || $addresses === []) {
            return ['', $count];
        }

        $formatted = [];
        foreach ($addresses as $address) {
            $formatted[] = $this->formatOne($address->getAddress(), $mode);
        }

        // Deduplicate only where collapsing is the point: in `domain` mode ten
        // recipients at one provider are one useful fact, not ten. In `full` and
        // `hashed` mode every entry is distinct information.
        if ($mode === 'domain') {
            $formatted = array_values(array_unique($formatted));
        }

        return [$this->truncate(implode(', ', $formatted)), $count];
    }

    private function formatOne(string $address, string $mode): string
    {
        return match ($mode) {
            'hashed' => $this->hash($address),
            'domain' => $this->domain($address),
            default => $address,
        };
    }

    /**
     * HMAC, not a bare hash.
     *
     * A plain sha256() of an e-mail address is not pseudonymisation — the
     * address space is enumerable and rainbow tables for common addresses are
     * trivial, so the digest is simply a reversible identifier. Keying it with
     * the instance's encryptionKey via HashService is what makes the value
     * defensible: without that key the digest cannot be traced back to a person,
     * and it still compares equal for equal addresses within one installation.
     */
    private function hash(string $address): string
    {
        $hash = $this->hashService->hmac(strtolower($address), self::HMAC_SECRET, HashAlgo::SHA256);

        return substr($hash, 0, self::HASH_LENGTH);
    }

    /**
     * Keeps the part after the last "@" — never the local part, which is the
     * identifying half. An address without "@" yields nothing rather than
     * leaking itself through the fallback.
     */
    private function domain(string $address): string
    {
        $position = strrpos($address, '@');
        if ($position === false) {
            return '?';
        }

        return '@' . strtolower(substr($address, $position + 1));
    }

    private function truncate(string $value): string
    {
        return mb_strlen($value) > self::MAX_LENGTH
            ? mb_substr($value, 0, self::MAX_LENGTH - 1) . '…'
            : $value;
    }
}
