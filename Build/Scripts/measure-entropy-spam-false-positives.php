#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

/**
 * Measures how many real German words EntropySpamValidator mistakes for
 * machine-generated text.
 *
 * The validator's thresholds — `maximumConsonantRun` above all — were chosen
 * against a number ("0.21% of all words of twelve letters or more"), and a
 * number nobody can reproduce turns into folklore the first time someone wants
 * to tune the check. This script recomputes it against the real validator, not
 * a reimplementation of it, so the docblocks can be checked rather than
 * believed.
 *
 * Each word is submitted on its own, which is the *pessimistic* case: the
 * `gibberishShare` denominator is then the word itself, so a single flagged
 * word rejects the submission. In prose the same word is diluted well below the
 * 25% threshold — that is what makes the share exist.
 *
 * Usage:
 *   php Build/Scripts/measure-entropy-spam-false-positives.php \
 *       [--dictionary=/usr/share/hunspell/de_DE.dic] \
 *       [--minimum-length=8] [--show=15] [--option=name:value]...
 *
 * The dictionary is a hunspell `.dic`: a count on the first line, then one word
 * per line with optional `/AFFIXFLAGS`. Debian/SUSE ship it in
 * `hunspell-de-de`; it is ISO-8859-1 unless its header says otherwise.
 */

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer install first — vendor/autoload.php is missing.\n");
    exit(1);
}
require $autoload;

$options = [
    'dictionary' => '/usr/share/hunspell/de_DE.dic',
    'minimum-length' => 8,
    'show' => 15,
];
$validatorOptions = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--([a-z-]+)=(.*)$/', $argument, $matches)) {
        fwrite(STDERR, sprintf("Unrecognised argument: %s\n", $argument));
        exit(1);
    }
    [, $name, $value] = $matches;
    if ($name === 'option') {
        [$optionName, $optionValue] = array_pad(explode(':', $value, 2), 2, null);
        $validatorOptions[$optionName] = is_numeric($optionValue) ? $optionValue + 0 : $optionValue;
        continue;
    }
    if (!array_key_exists($name, $options)) {
        fwrite(STDERR, sprintf("Unknown option: --%s\n", $name));
        exit(1);
    }
    $options[$name] = $value;
}

$dictionary = (string)$options['dictionary'];
if (!is_readable($dictionary)) {
    fwrite(STDERR, sprintf("Cannot read dictionary %s (install hunspell-de-de).\n", $dictionary));
    exit(1);
}

$minimumLength = (int)$options['minimum-length'];
$words = [];
$handle = fopen($dictionary, 'rb');
fgets($handle); // the word count on line one
while (($line = fgets($handle)) !== false) {
    $word = trim(explode('/', trim($line))[0]);
    if ($word === '') {
        continue;
    }
    if (!mb_check_encoding($word, 'UTF-8')) {
        $word = mb_convert_encoding($word, 'UTF-8', 'ISO-8859-1');
    }
    // Hyphenated compounds are two tokens to the validator, which cuts at every
    // non-alphanumeric character. Measure what it actually sees.
    foreach (preg_split('/[^\p{L}\p{N}]+/u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        if (mb_strlen($token) >= $minimumLength) {
            $words[$token] = true;
        }
    }
}
fclose($handle);
$words = array_keys($words);

$validator = new TYPO3\CMS\Form\Validation\EntropySpamValidator();
// A literal message, because the branch default is an LLL: reference and
// resolving one needs a LanguageService, i.e. a booted TYPO3. Only the verdict
// matters here, never the wording.
$validator->setOptions($validatorOptions + ['errorMessage' => 'rejected']);

$flagged = [];
foreach ($words as $word) {
    if ($validator->validate(['message' => $word])->hasErrors()) {
        $flagged[] = $word;
    }
}

$total = count($words);
printf(
    "%s\n  tokens of >= %d characters: %d\n  flagged as machine-generated: %d (%.2f%%)\n",
    $dictionary,
    $minimumLength,
    $total,
    count($flagged),
    $total > 0 ? 100 * count($flagged) / $total : 0.0,
);
if ($validatorOptions !== []) {
    printf("  non-default options: %s\n", json_encode($validatorOptions));
}
$show = (int)$options['show'];
if ($show > 0 && $flagged !== []) {
    printf("  examples: %s\n", implode(', ', array_slice($flagged, 0, $show)));
}
