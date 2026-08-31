<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Fixtures;

/**
 * Single source of truth for the "bot vs. human" corpus that exercises the
 * EntropySpamValidator. Shared between the unit test (validator in isolation)
 * and the functional tests (validator wired as a form-level validator and
 * driven through the FormRuntime), so both assert against the exact same
 * payloads and a change to what counts as spam only has to be made once.
 */
final class SpamCorpus
{
    /**
     * Gibberish tokens that MUST be rejected when placed in a free-text field.
     * These mirror the real-world reporting sample (mid-word case flips, low
     * vowel ratio, consonant clusters, keyboard mashing).
     *
     * @return array<string, array{string}>
     */
    public static function botTokens(): array
    {
        return [
            'random mixed case' => ['vOYhcWlrcTafTMSelBkM'],
            'random mixed case 2' => ['goIuMokMxpTCeKnJfIq'],
            'all upper consonants' => ['XQVBLMPKWZRNTCYHF'],
            'lowercase keyboard mash' => ['asdkjhqweuiozxcvbnm'],
            'random camel case' => ['aSdKjHqWeUiOzXcVbN'],
        ];
    }

    /**
     * Legitimate submissions that MUST be accepted — natural prose, names,
     * compound words, all-caps words and URLs that would otherwise look
     * high-entropy.
     *
     * @return array<string, array{array<string, string>}>
     */
    public static function humanSubmissions(): array
    {
        return [
            'normal contact' => [[
                'name' => 'Maria Lindqvist',
                'email' => 'maria@example.com',
                'message' => 'Bitte rufen Sie mich am Montag zurueck, vielen Dank!',
            ]],
            'hyphenated name' => [[
                'name' => 'Wolfgang Schmidt-Hubermann',
                'message' => 'Ich interessiere mich fuer Ihr Angebot.',
            ]],
            'german compound word' => [[
                'message' => 'Donaudampfschifffahrtsgesellschaft',
            ]],
            'lowercase long word' => [[
                'message' => 'unternehmensberatung',
            ]],
            'all-caps words' => [[
                'name' => 'WOLFGANG ABTEILUNGSLEITER',
            ]],
            'url in message' => [[
                'message' => 'Siehe https://example.com/produkte fuer Details.',
            ]],
            // Added after this corpus was first written, and the reason the
            // consonant-run condition exists: entropy and vowel ratio alone flag
            // a German compound, and one such word in an otherwise plainly human
            // enquiry turned a real submission away. Measured against the
            // hunspell de_DE list the two conditions alone flag 3.44% of all
            // words of twelve letters or more, the consonant run brings that to
            // 0.21%.
            'consonant heavy compound' => [[
                'message' => 'Brandschutzklappe',
            ]],
            'compound with low vowel ratio' => [[
                'subject' => 'Bildschirmfoto',
            ]],
            'short enquiry naming a test mailbox' => [[
                'message' => 'Die Absenderadresse ist ein Testpostfach.',
            ]],
        ];
    }
}
