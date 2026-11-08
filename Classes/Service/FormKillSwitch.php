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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * The kill switch: the one place that knows whether a form is currently
 * accepting anything, and the only place that writes that decision.
 *
 * Why this exists at all. The extension already carries four spam layers, but
 * every one of them is a *filter* — it decides per submission, and it only
 * helps once somebody has found and configured the right one. Under a live
 * flood the question is a different one: "make it stop, now". Before this the
 * only answers were hiding the plugin element (per page, loses the
 * configuration, and does nothing against a bot POSTing straight at the action
 * URI) or a hotfix deployment. Neither is available at three in the morning.
 *
 * Three levels, and a form is blocked as soon as ANY of them says so:
 * globally, per site, per form identifier. That asymmetry is deliberate —
 * during an incident you want the coarse switch to win, and you never want a
 * narrow "enabled" entry to quietly re-open something you shut off at the top.
 *
 * Keyed by the FORM identifier, not the persistence identifier: that is what
 * editors see, what the CLI user types, and the only one of the two available
 * on both the render and the submit path. Known consequence: two forms sharing
 * an identifier across storages are switched together.
 *
 * State lives in sys_registry rather than in extension configuration, site
 * settings or YAML, for one reason: an emergency stop must not be a
 * deployment. It is operational state, it takes effect on the next request,
 * and it deliberately does not travel with the repository — a form switched
 * off on production because of an attack there must not come back disabled on
 * the next developer's machine.
 *
 * Every entry may carry `until`. Expiry is evaluated on READ, not by a
 * scheduler task. A kill switch whose release depends on a cron job that may
 * not be running is built the wrong way round; and because a blocked form
 * renders uncached (see BlockDisabledForms), no stale notice can survive in
 * the page cache past the expiry either.
 */
final class FormKillSwitch implements SingletonInterface
{
    public const REGISTRY_NAMESPACE = 'tx_form';
    public const REGISTRY_KEY = 'killSwitch';

    /**
     * Cache tag on every page that rendered any form, plus a per-form tag.
     * Flipping the switch flushes by tag instead of dropping the whole page
     * cache — on a large site that difference is the one between an emergency
     * stop and a second outage.
     */
    public const CACHE_TAG = 'tx_form';
    public const CACHE_TAG_FORM_PREFIX = 'tx_form_';

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_SITE = 'sites';
    public const SCOPE_FORM = 'forms';

    /**
     * @var array{threshold: int, windowMinutes: int, durationMinutes: int, notify: string}
     */
    public const AUTO_DISABLE_DEFAULTS = [
        'threshold' => 100,
        'windowMinutes' => 5,
        'durationMinutes' => 60,
        'notify' => '',
    ];

    /**
     * Request-local copy of the registry entry. The registry itself caches per
     * namespace, but this keeps the (frequent) isDisabled() calls out of even
     * that lookup, and guarantees one consistent answer for the whole request.
     *
     * @var array<string, mixed>|null
     */
    private ?array $state = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * The question the frontend asks. `$siteIdentifier` may be empty when no
     * site could be resolved (CLI, tests) — the global level still applies.
     */
    public function isDisabled(string $formIdentifier, string $siteIdentifier = ''): bool
    {
        return $this->isActive($this->entry(self::SCOPE_GLOBAL))
            || ($siteIdentifier !== '' && $this->isActive($this->entry(self::SCOPE_SITE, $siteIdentifier)))
            || ($formIdentifier !== '' && $this->isActive($this->entry(self::SCOPE_FORM, $formIdentifier)));
    }

    /**
     * The message to show, most specific level first. An empty string means
     * "no custom text was entered anywhere" — the caller then falls back to the
     * localized default, which is the only multi-language-correct option.
     */
    public function getMessage(string $formIdentifier, string $siteIdentifier = ''): string
    {
        $candidates = [
            $formIdentifier !== '' ? $this->entry(self::SCOPE_FORM, $formIdentifier) : null,
            $siteIdentifier !== '' ? $this->entry(self::SCOPE_SITE, $siteIdentifier) : null,
            $this->entry(self::SCOPE_GLOBAL),
        ];

        foreach ($candidates as $entry) {
            if ($entry === null || !$this->isActive($entry)) {
                continue;
            }
            $message = trim((string)($entry['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return '';
    }

    /**
     * @param string|null $identifier null on the global scope
     * @param int|null $until unix timestamp the block expires at, null = open ended
     */
    public function disable(
        string $scope,
        ?string $identifier,
        string $by,
        string $message = '',
        ?int $until = null,
    ): void {
        $state = $this->readState();
        $entry = [
            'disabled' => true,
            'message' => $message,
            'since' => $this->now(),
            'until' => $until,
            'by' => $by,
        ];

        if ($scope === self::SCOPE_GLOBAL) {
            $state[self::SCOPE_GLOBAL] = $entry;
        } else {
            if ((string)$identifier === '') {
                return;
            }
            $state[$scope][(string)$identifier] = $entry;
        }

        $this->writeState($state);
        $this->flush($scope, $identifier);
    }

    public function enable(string $scope, ?string $identifier = null): void
    {
        $state = $this->readState();

        if ($scope === self::SCOPE_GLOBAL) {
            unset($state[self::SCOPE_GLOBAL]);
        } else {
            if ((string)$identifier === '') {
                return;
            }
            unset($state[$scope][(string)$identifier]);
        }

        $this->writeState($state);
        $this->flush($scope, $identifier);
    }

    /**
     * Everything the backend module and `form:status` need, with expired
     * entries already dropped so a reader never has to do that arithmetic.
     *
     * @return array{global: array<string, mixed>|null, sites: array<string, array<string, mixed>>, forms: array<string, array<string, mixed>>, autoDisable: array<string, mixed>}
     */
    public function getState(): array
    {
        $state = $this->readState();

        $global = $this->entry(self::SCOPE_GLOBAL);

        return [
            'global' => $global !== null && $this->isActive($global) ? $global : null,
            'sites' => $this->activeEntries($state[self::SCOPE_SITE] ?? []),
            'forms' => $this->activeEntries($state[self::SCOPE_FORM] ?? []),
            'autoDisable' => $this->getAutoDisableSettings(),
        ];
    }

    /**
     * @return array{threshold: int, windowMinutes: int, durationMinutes: int, notify: string}
     */
    public function getAutoDisableSettings(): array
    {
        $stored = $this->readState()['autoDisable'] ?? [];
        $stored = is_array($stored) ? $stored : [];

        return [
            'threshold' => max(1, (int)($stored['threshold'] ?? self::AUTO_DISABLE_DEFAULTS['threshold'])),
            'windowMinutes' => max(1, (int)($stored['windowMinutes'] ?? self::AUTO_DISABLE_DEFAULTS['windowMinutes'])),
            'durationMinutes' => max(1, (int)($stored['durationMinutes'] ?? self::AUTO_DISABLE_DEFAULTS['durationMinutes'])),
            'notify' => trim((string)($stored['notify'] ?? self::AUTO_DISABLE_DEFAULTS['notify'])),
        ];
    }

    public function setAutoDisableSettings(int $threshold, int $windowMinutes, int $durationMinutes, string $notify): void
    {
        $state = $this->readState();
        $state['autoDisable'] = [
            'threshold' => max(1, $threshold),
            'windowMinutes' => max(1, $windowMinutes),
            'durationMinutes' => max(1, $durationMinutes),
            'notify' => trim($notify),
        ];
        $this->writeState($state);
    }

    /**
     * Cache tag names may only contain a restricted character set, while a form
     * identifier is whatever the editor typed. Sanitising rather than hashing
     * keeps the tag readable in a cache dump; a collision between two forms
     * that differ only in a stripped character costs one extra flushed page.
     *
     * A run of rejected characters collapses into a single underscore, so the
     * result does not depend on how many BYTES a character happens to occupy —
     * one umlaut would otherwise produce two underscores and the tag would look
     * like a bug.
     */
    public static function cacheTagForForm(string $formIdentifier): string
    {
        return self::CACHE_TAG_FORM_PREFIX . preg_replace('/[^A-Za-z0-9_-]+/', '_', $formIdentifier);
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function isActive(?array $entry): bool
    {
        if ($entry === null || empty($entry['disabled'])) {
            return false;
        }
        $until = $entry['until'] ?? null;

        return $until === null || (int)$until > $this->now();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(string $scope, ?string $identifier = null): ?array
    {
        $state = $this->readState();

        if ($scope === self::SCOPE_GLOBAL) {
            $entry = $state[self::SCOPE_GLOBAL] ?? null;
        } else {
            $entry = $state[$scope][(string)$identifier] ?? null;
        }

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param mixed $entries
     * @return array<string, array<string, mixed>>
     */
    private function activeEntries(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        return array_filter(
            $entries,
            fn(mixed $entry): bool => is_array($entry) && $this->isActive($entry)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        if ($this->state === null) {
            $stored = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, []);
            $this->state = is_array($stored) ? $stored : [];
        }

        return $this->state;
    }

    /**
     * Expired entries are pruned on every write. Doing it on read would mean
     * writing to the database from a frontend request, which is exactly what a
     * site under attack does not need.
     *
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void
    {
        foreach ([self::SCOPE_SITE, self::SCOPE_FORM] as $scope) {
            if (!is_array($state[$scope] ?? null)) {
                unset($state[$scope]);
                continue;
            }
            $state[$scope] = array_filter(
                $state[$scope],
                fn(mixed $entry): bool => is_array($entry) && $this->isActive($entry)
            );
            if ($state[$scope] === []) {
                unset($state[$scope]);
            }
        }
        if (isset($state[self::SCOPE_GLOBAL]) && !$this->isActive($state[self::SCOPE_GLOBAL])) {
            unset($state[self::SCOPE_GLOBAL]);
        }

        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $state);
        $this->state = $state;
    }

    /**
     * Turning a switch ON has to drop the pages that were cached while it was
     * off — they still contain a working form. Turning it OFF needs no flush at
     * all, because a blocked form is rendered uncached, so nothing holding the
     * notice ever entered the cache.
     */
    private function flush(string $scope, ?string $identifier): void
    {
        $tag = $scope === self::SCOPE_FORM && (string)$identifier !== ''
            ? self::cacheTagForForm((string)$identifier)
            : self::CACHE_TAG;

        $this->cacheManager->flushCachesInGroupByTag('pages', $tag);
    }

    private function now(): int
    {
        return (int)($GLOBALS['EXEC_TIME'] ?? time());
    }
}
