<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Event;

/**
 * Dispatched from ConfigurationManager::getYamlConfiguration() AFTER the
 * full YAML form-prototype configuration has been resolved (and after the
 * core cache has been read/written). Listeners may modify the resulting
 * array — e.g. inject runtime-computed values into the form-editor
 * configuration (site languages, available filemounts, dynamic option
 * lists).
 *
 * The dispatch fires on every call, NOT only on cache-miss — listeners
 * keep the YAML "live" against external state that can change without
 * invalidating the YAML cache (typical example: site language list).
 * Listeners must therefore be cheap; expensive computations should cache
 * their own results.
 *
 * Promoted from wapplersystems/form_extended into the fork as part of
 * Phase 2 of the form_extended migration.
 *
 * @final
 */
final class AfterYamlConfigurationLoadedEvent
{
    public function __construct(
        public array $yamlConfiguration,
    ) {}
}
