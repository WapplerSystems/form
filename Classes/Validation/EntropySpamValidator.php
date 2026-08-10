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
 * Rejects form submissions whose human-entered free text looks machine-generated.
 *
 * Only genuine free-text fields are analysed (Text, Textarea, Email, Url,
 * Telephone). Values coming from fixed-choice elements — single/multi select,
 * radio buttons, (multi)checkboxes, country select, date/time, numbers, etc. —
 * are NOT part of the calculation: their values are predefined option strings,
 * not something a spammer types, so including them only dilutes the signal.
 * (When `textFieldIdentifiers` is configured it takes precedence and limits the
 * analysis to exactly those identifiers.)
 *
 * Two independent checks run:
 *
 *  1. Repetitive spam — the combined free text has a Shannon entropy below
 *     `minimumEntropy` (e.g. "aaaaaaaa", "hahahaha"), or, as a safety net for
 *     long uniform-random blobs, above the absolute `maximumEntropy`.
 *
 *  2. Random gibberish — a single free-text token (e.g. "vOYhcWlrcTafTMSelBkM",
 *     "goIuMokMxpTCeKnJfIq") that looks brute-force generated. Absolute Shannon
 *     entropy cannot catch this for short fields because it is capped by
 *     log2(length); a 20-character string can never exceed ~4.3 bits/char. We
 *     therefore use the *normalized* entropy ratio (entropy / log2(length)),
 *     which approaches 1.0 for uniform-random strings while natural words and
 *     names — thanks to letter repetition — stay well below. To avoid blocking
 *     legitimate long words a token is only rejected when it additionally shows
 *     a machine-like shape: random mid-token upper/lower-case flips, or an
 *     unnaturally low vowel ratio.
 *
 * Submissions whose combined free text is shorter than `minimumLength` skip the
 * entropy band (too short for a meaningful estimate); the gibberish check still
 * runs per token.
 */
final class EntropySpamValidator extends AbstractFormAwareValidator
{
    /**
     * Element types whose submitted value is genuine free text and therefore
     * relevant to spam analysis. Everything else (choices, dates, numbers,
     * passwords, uploads, honeypot, …) is ignored. Compared case-insensitively.
     *
     * @var list<string>
     */
    private const FREE_TEXT_TYPES = ['text', 'textarea', 'email', 'url', 'telephone'];

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
            'Reject if Shannon entropy of combined text is above this (bits/char). Safety net for long uniform-random blobs.',
            'float',
        ],
        'maximumEntropyRatio' => [
            0.9,
            'Reject a single free-text token whose normalized entropy (entropy / log2(length)) reaches this. Catches short random-looking gibberish that the absolute entropy band cannot.',
            'float',
        ],
        'gibberishTokenLength' => [
            12,
            'Minimum length of a (letter-only) token before the gibberish ratio check is applied. Shorter tokens are too volatile to judge.',
            'integer',
        ],
        'minimumVowelRatio' => [
            0.3,
            'A high-entropy token is only treated as gibberish when it also has mixed-case flips or a vowel ratio below this value.',
            'float',
        ],
        'textFieldIdentifiers' => [
            [],
            'Optional whitelist of element identifiers to analyse. Empty array = auto-detect all free-text elements.',
            'array',
        ],
        'minimumLength' => [
            20,
            'Skip the combined-entropy band when the combined free text is shorter than this. Short inputs produce unreliable entropy estimates.',
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
        $fields = $this->collectFreeTextFields($value);
        if ($fields === []) {
            return;
        }

        // Check 2 (random gibberish) runs per field and is independent of the
        // combined-length gate, so a single random field is caught on its own.
        foreach ($fields as $fieldValue) {
            if ($this->containsGibberishToken($fieldValue)) {
                $this->reject();
                return;
            }
        }

        $combined = implode(' ', $fields);
        if (mb_strlen($combined) < (int)$this->options['minimumLength']) {
            return;
        }

        // Check 1 (repetitive / overall-random band).
        $entropy = $this->shannonEntropy($combined);
        if ($entropy < (float)$this->options['minimumEntropy']
            || $entropy > (float)$this->options['maximumEntropy']
        ) {
            $this->reject();
        }
    }

    private function reject(): void
    {
        // Code derived from class name + a unique numeric marker so listeners
        // can suppress this specific check by error code.
        $this->addError((string)$this->options['errorMessage'], 1717686001);
    }

    /**
     * Returns the submitted free-text values keyed by element identifier.
     *
     * @param mixed $value
     * @return array<string, string>
     */
    private function collectFreeTextFields($value): array
    {
        if (!is_array($value)) {
            return is_string($value) && $value !== '' ? ['' => $value] : [];
        }
        $whitelist = $this->options['textFieldIdentifiers'] ?? [];
        $useWhitelist = is_array($whitelist) && $whitelist !== [];

        $fields = [];
        foreach ($value as $identifier => $fieldValue) {
            if (!is_string($fieldValue) || $fieldValue === '') {
                continue;
            }
            $identifier = (string)$identifier;
            if ($useWhitelist) {
                if (!in_array($identifier, $whitelist, true)) {
                    continue;
                }
            } elseif (!$this->isFreeTextElement($identifier)) {
                continue;
            }
            $fields[$identifier] = $fieldValue;
        }
        return $fields;
    }

    /**
     * Whether the element with the given identifier is a free-text element.
     * Falls back to "include" when no form context is available (e.g. the
     * validator is used standalone in tests).
     */
    private function isFreeTextElement(string $identifier): bool
    {
        if (!isset($this->formRuntime)) {
            return true;
        }
        $element = $this->formRuntime->getFormDefinition()->getElementByIdentifier($identifier);
        if ($element === null) {
            return false;
        }
        return in_array(strtolower($element->getType()), self::FREE_TEXT_TYPES, true);
    }

    /**
     * True when any whitespace/punctuation-delimited letter run looks like
     * machine-generated gibberish.
     */
    private function containsGibberishToken(string $text): bool
    {
        $minLength = (int)$this->options['gibberishTokenLength'];
        $maxRatio = (float)$this->options['maximumEntropyRatio'];
        $minVowelRatio = (float)$this->options['minimumVowelRatio'];

        $tokens = preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < $minLength) {
                continue;
            }
            if ($this->normalizedEntropy($token) < $maxRatio) {
                continue;
            }
            if ($this->hasMixedCaseFlip($token) || $this->vowelRatio($token) < $minVowelRatio) {
                return true;
            }
        }
        return false;
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

    /**
     * Shannon entropy relative to the maximum possible for the token's length
     * (log2(length)). ~1.0 for uniform-random strings, lower for natural words.
     */
    private function normalizedEntropy(string $token): float
    {
        $length = mb_strlen($token);
        if ($length < 2) {
            return 0.0;
        }
        return $this->shannonEntropy($token) / log($length, 2);
    }

    /**
     * A lower-case letter immediately followed by an upper-case one ("vOYhc"),
     * typical of random-case machine output and almost never of human input.
     */
    private function hasMixedCaseFlip(string $token): bool
    {
        return (bool)preg_match('/\p{Ll}\p{Lu}/u', $token);
    }

    private function vowelRatio(string $token): float
    {
        $length = mb_strlen($token);
        if ($length === 0) {
            return 1.0;
        }
        preg_match_all('/[aeiouyäöüAEIOUYÄÖÜ]/u', $token, $matches);
        return count($matches[0]) / $length;
    }
}
