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
     * The same salad drawn from an alphabet that includes digits. Kept apart
     * from botTokens() because these are the samples that defeated the
     * letter-only tokenizer: a digit in the middle cut every one of them into
     * fragments of four or five characters, which no length threshold worth
     * having can judge.
     *
     * The first four are the fields of the submission that got through on a
     * live cancellation form and prompted the check.
     *
     * @return array<string, array{string}>
     */
    public static function mixedAlnumBotTokens(): array
    {
        return [
            'reported name' => ['ZYWVj7hyXv'],
            'reported first name' => ['AmJj19D9Y5'],
            'reported phone' => ['9KI0nB1YVM'],
            'reported contract designation' => ['JuT8l9hsQJ'],
            'digit in the middle of camel salad' => ['aBcDeFgHiJ7kLmNoPqRsT'],
            'alternating without case flip' => ['q1w2e3r4t5'],
            'short mixed salad' => ['xk3Mp9Qz'],
        ];
    }

    /**
     * Letters-and-digits values a real visitor types, which MUST be accepted.
     *
     * This is the expensive direction of the alphanumeric check: a contract
     * designation, a customer number, an IBAN or a course name with a year in
     * it is as repetition-free as generated salad, and on a cancellation form a
     * false positive costs the site owner a deadline rather than a spam mail.
     *
     * @return array<string, array{array<string, string>}>
     */
    public static function legitimateAlnumSubmissions(): array
    {
        return [
            'contract number' => [['message' => 'Vertrag Nr. AS-2024-1234']],
            'course with year' => [['message' => 'Examenskurs2026']],
            'course with term' => [['message' => 'Assessorkurs 2026/1']],
            'iban' => [['message' => 'DE89370400440532013000']],
            'customer number' => [['message' => 'MITGLIEDSNR 4711']],
            'abbreviation with digit' => [['message' => 'BGB-AT2']],
            'product with digits' => [['message' => 'iPhone13']],
            'years spanned' => [['message' => 'Vertrag2019bis2026']],
            'inner capital and year' => [['message' => 'StudentIn2026']],
            'phone number' => [['phone' => '0170 1234567']],
            'phone number international' => [['phone' => '+49 (0)89 123456-78']],
            'phone number bare' => [['phone' => '4194840183']],
            'order number with suffix' => [['message' => 'Kurs-Nr 8823-A']],
        ];
    }

    /**
     * The reported submission in full: every field salad with a digit or two
     * dropped in, the e-mail address the only value that looks real. Its
     * combined free text has an entropy of 5.074 bits per character, i.e. inside
     * the permitted band, so the per-token verdict is the only thing that can
     * catch it.
     *
     * @return array<string, string>
     */
    public static function reportedDigitSeededSubmission(): array
    {
        return [
            'text-1' => 'ZYWVj7hyXv',
            'text-2' => 'AmJj19D9Y5',
            'email-1' => 'qsdixon@yahoo.com',
            'text-3' => '9KI0nB1YVM',
            'text-4' => 'JuT8l9hsQJ',
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
