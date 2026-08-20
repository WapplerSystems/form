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

use TYPO3\CMS\Form\Enum\MailLogStatus;

/**
 * A normalised, whitelisted filter for the mail log listing.
 *
 * Everything arrives from a query string, so nothing here trusts its input:
 * unparsable dates fall back to the default window, an unknown status becomes
 * "no status filter", and identifiers are matched exactly rather than pattern
 * matched. That keeps the module's one raw-SQL surface small and boring.
 *
 * Deliberately a single object rather than five controller arguments, so the
 * Fluid partials can pass `filter` through untouched and stay reusable by the
 * second (validation statistics) view that this module is built to grow.
 */
readonly class MailLogDemand
{
    /**
     * Days shown when no range was chosen.
     *
     * A bounded default matters: the sibling validation-log table already holds
     * five figures, and a module whose first screen tries to page through
     * everything ever recorded reads as broken rather than as thorough.
     */
    public const DEFAULT_DAYS = 30;

    /**
     * Pseudo-status meaning "everything a human should look at" — reported
     * failures plus rows abandoned in a non-terminal state. Kept as one concept
     * so the module filter and the CLI check cannot drift apart.
     */
    public const STATUS_PROBLEMS = 'problems';

    private function __construct(
        public int $from,
        public int $to,
        public ?MailLogStatus $status,
        public bool $problemsOnly,
        public string $formIdentifier,
        public string $finisherIdentifier,
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

        $rawStatus = is_string($filter['status'] ?? null) ? $filter['status'] : '';
        $problemsOnly = $rawStatus === self::STATUS_PROBLEMS;
        $status = $problemsOnly ? null : MailLogStatus::tryFrom((int)$rawStatus);
        // tryFrom((int)'') would resolve to PENDING, which is not what an empty
        // filter means.
        if ($rawStatus === '') {
            $status = null;
        }

        return new self(
            from: $from,
            to: $to,
            status: $status,
            problemsOnly: $problemsOnly,
            formIdentifier: self::clean($filter['formIdentifier'] ?? null),
            finisherIdentifier: self::clean($filter['finisherIdentifier'] ?? null),
        );
    }

    /**
     * The filter as it goes back into links, so pagination and sorting keep the
     * current selection without the controller restating it.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from' => date('Y-m-d', $this->from),
            'to' => date('Y-m-d', $this->to),
            'status' => $this->problemsOnly ? self::STATUS_PROBLEMS : ((string)($this->status?->value ?? '')),
            'formIdentifier' => $this->formIdentifier,
            'finisherIdentifier' => $this->finisherIdentifier,
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

    private static function clean(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
