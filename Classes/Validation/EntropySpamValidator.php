<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Validation;

/**
 * Rejects form submissions whose combined text content falls outside a
 * configurable Shannon-entropy band:
 *  - entropy below `minimumEntropy` indicates repetitive spam (aaaaaaaaa,
 *    hahahaha) typical of low-effort bots
 *  - entropy above `maximumEntropy` indicates uniform-random gibberish
 *    typical of brute-force bots filling fields with random strings
 *
 * Human-written text in most languages falls between roughly 3.5 and 5.0
 * bits/character; the default band is intentionally wider so legitimate
 * short submissions, code snippets and multilingual content aren't blocked.
 *
 * Submissions shorter than `minimumLength` characters are skipped — too
 * short to yield meaningful entropy estimates.
 */
final class EntropySpamValidator extends AbstractFormAwareValidator
{
    /**
     * @var array<string, array{0: mixed, 1: string, 2?: string}>
     */
    protected $supportedOptions = [
        'minimumEntropy' => [
            1.8,
            'Reject if Shannon entropy of combined text is below this (bits/char). Catches repetitive spam.',
            'float',
        ],
        'maximumEntropy' => [
            5.8,
            'Reject if Shannon entropy of combined text is above this (bits/char). Catches random gibberish.',
            'float',
        ],
        'textFieldIdentifiers' => [
            [],
            'Optional whitelist of element identifiers to include in the entropy calculation. Empty array = all string values.',
            'array',
        ],
        'minimumLength' => [
            20,
            'Skip the entropy check when the combined text is shorter than this. Short inputs produce unreliable entropy estimates.',
            'integer',
        ],
        'errorMessage' => [
            'Your submission could not be accepted. Please rephrase and try again.',
            'Error message shown when the submission is rejected.',
            'string',
        ],
    ];

    /**
     * @param mixed $value Expected to be the array of submitted form values
     *                    (keyed by element identifier). When invoked by
     *                    RunFormLevelValidators this is FormRuntime's
     *                    formState->getFormValues().
     */
    protected function isValid(mixed $value): void
    {
        $text = $this->collectText($value);
        $length = mb_strlen($text);
        if ($length < (int)$this->options['minimumLength']) {
            return;
        }

        $entropy = $this->shannonEntropy($text);
        $min = (float)$this->options['minimumEntropy'];
        $max = (float)$this->options['maximumEntropy'];
        if ($entropy < $min || $entropy > $max) {
            // Code derived from class name + a unique numeric marker so
            // listeners can suppress this specific check by error code.
            $this->addError(
                (string)$this->options['errorMessage'],
                1717686001
            );
        }
    }

    /**
     * @param mixed $value
     */
    private function collectText($value): string
    {
        if (!is_array($value)) {
            return is_string($value) ? $value : '';
        }
        $whitelist = $this->options['textFieldIdentifiers'] ?? [];
        $useWhitelist = is_array($whitelist) && $whitelist !== [];

        $texts = [];
        foreach ($value as $identifier => $fieldValue) {
            if ($useWhitelist && !in_array($identifier, $whitelist, true)) {
                continue;
            }
            if (is_string($fieldValue) && $fieldValue !== '') {
                $texts[] = $fieldValue;
            }
        }
        return implode(' ', $texts);
    }

    private function shannonEntropy(string $text): float
    {
        $chars = mb_str_split($text);
        $length = count($chars);
        if ($length === 0) {
            return 0.0;
        }
        $frequencies = array_count_values($chars);
        $entropy = 0.0;
        foreach ($frequencies as $count) {
            $p = $count / $length;
            $entropy -= $p * log($p, 2);
        }
        return $entropy;
    }
}
