<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Form\Tests\Fixtures\SpamCorpus;
use TYPO3\CMS\Form\Tests\Functional\Framework\EntropyFormTestCase;

/**
 * End-to-end coverage for the EntropySpam filter as it protects the live
 * contact form: wired as a `formLevelValidators` entry (maximumEntropyRatio
 * 0.85) and driven through the full FormRuntime validation chain — not the
 * validator in isolation (that is {@see \TYPO3\CMS\Form\Tests\Unit\Validation\EntropySpamValidatorTest}).
 *
 * Guards the exact configuration that rejected 344 bot submissions on live.
 */
final class EntropySpamFormLevelValidatorTest extends EntropyFormTestCase
{
    private const ENTROPY_ERROR_CODE = 1717686001;

    /**
     * @return array<string, array{string}>
     */
    public static function botTokenProvider(): array
    {
        return SpamCorpus::botTokens();
    }

    #[Test]
    #[DataProvider('botTokenProvider')]
    public function gibberishInAFreeTextFieldIsRejected(string $token): void
    {
        $result = $this->validateSubmission(['message' => $token]);

        self::assertTrue($result->hasErrors(), sprintf('Expected "%s" to be rejected.', $token));
        self::assertContains(
            self::ENTROPY_ERROR_CODE,
            $this->errorCodes($result),
            sprintf('Expected the EntropySpam error code for "%s".', $token),
        );
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function humanSubmissionProvider(): array
    {
        return SpamCorpus::humanSubmissions();
    }

    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('humanSubmissionProvider')]
    public function legitimateSubmissionIsAccepted(array $values): void
    {
        $result = $this->validateSubmission($values);

        self::assertFalse(
            $result->hasErrors(),
            'Legitimate submission was wrongly rejected: ' . json_encode($values),
        );
    }

    #[Test]
    public function gibberishInAFixedChoiceFieldIsNotAnalysed(): void
    {
        // A gibberish value in the SingleSelect "topic" must not trigger the
        // entropy filter (fixed-choice values are never analysed).
        $result = $this->validateSubmission(['topic' => 'vOYhcWlrcTafTMSelBkM']);

        self::assertNotContains(
            self::ENTROPY_ERROR_CODE,
            $this->errorCodes($result),
            'A fixed-choice value must never be analysed for entropy.',
        );
    }
}
