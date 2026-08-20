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

use TYPO3\CMS\Form\Enum\ValidationAudience;

/**
 * Normalised filter for the validation-statistics view.
 *
 * Same shape and same guarantees as MailLogDemand — everything arrives from a
 * query string, so unparsable input falls back to a default rather than
 * producing an empty screen, and the object round-trips through toArray() so
 * pagination and sorting links can carry the selection without the controller
 * restating it.
 */
readonly class ValidationStatsDemand
{
    public const DEFAULT_DAYS = 30;

    private function __construct(
        public int $from,
        public int $to,
        public ValidationAudience $audience,
        public string $formIdentifier,
    ) {}

    /**
     * @param array<string, mixed> $filter
     */
    public static function fromArray(array $filter, ?int $now = null): self
    {
        $now ??= time();
        $from = self::parseDate($filter['from'] ?? null) ?? $now - self::DEFAULT_DAYS * 86400;
        $to = self::parseDate($filter['to'] ?? null, endOfDay: true) ?? $now;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $audience = is_string($filter['audience'] ?? null)
            ? ValidationAudience::tryFrom($filter['audience'])
            : null;

        return new self(
            from: $from,
            to: $to,
            audience: $audience ?? ValidationAudience::ALL,
            formIdentifier: is_string($filter['formIdentifier'] ?? null) ? trim($filter['formIdentifier']) : '',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from' => date('Y-m-d', $this->from),
            'to' => date('Y-m-d', $this->to),
            'audience' => $this->audience->value,
            'formIdentifier' => $this->formIdentifier,
        ];
    }

    private static function parseDate(mixed $value, bool $endOfDay = false): ?int
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59)->getTimestamp() : $date->getTimestamp();
    }
}
