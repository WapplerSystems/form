<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\DTO;

/**
 * A normalised, whitelisted filter for the consent log listing.
 *
 * Same shape and same reasoning as MailLogDemand — nothing here trusts the
 * query string it came from.
 *
 * One difference matters: the default window is a year rather than the mail
 * log's month. The question this log answers is "did person X consent, and to
 * what", which is asked long after the submission — a 30-day default would hide
 * the answer in the very case the module exists for.
 */
readonly class ConsentLogDemand
{
    public const DEFAULT_DAYS = 365;

    private function __construct(
        public int $from,
        public int $to,
        public string $formIdentifier,
        public string $subject,
        public bool $givenOnly,
        public bool $withheldOnly,
    ) {}

    /**
     * @param array<string, mixed> $filter
     */
    public static function fromArray(array $filter, ?int $now = null): self
    {
        $now ??= time();
        $from = self::parseDate($filter['from'] ?? null) ?? $now - self::DEFAULT_DAYS * 86400;
        $to = self::parseDate($filter['to'] ?? null, endOfDay: true) ?? $now;

        // A reversed range is a slip, not an empty result set.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $given = is_string($filter['given'] ?? null) ? $filter['given'] : '';

        return new self(
            from: $from,
            to: $to,
            formIdentifier: self::asString($filter['form'] ?? null, 100),
            subject: self::asString($filter['subject'] ?? null, 255),
            givenOnly: $given === '1',
            withheldOnly: $given === '0',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $given = '';
        if ($this->givenOnly) {
            $given = '1';
        } elseif ($this->withheldOnly) {
            $given = '0';
        }

        return [
            'from' => date('Y-m-d', $this->from),
            'to' => date('Y-m-d', $this->to),
            'form' => $this->formIdentifier,
            'subject' => $this->subject,
            'given' => $given,
        ];
    }

    private static function asString(mixed $value, int $maxLength): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }

    private static function parseDate(mixed $value, bool $endOfDay = false): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime(trim($value) . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));

        return $timestamp === false ? null : $timestamp;
    }
}
