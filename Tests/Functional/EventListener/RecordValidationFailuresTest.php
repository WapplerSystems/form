<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Form\Tests\Fixtures\SpamCorpus;
use TYPO3\CMS\Form\Tests\Functional\Fixtures\Forms\EntropyContactForm;
use TYPO3\CMS\Form\Tests\Functional\Framework\EntropyFormTestCase;

/**
 * Covers the fork's `RecordValidationFailures` listener — the mechanism that
 * feeds `tx_form_validation_log` and therefore the live bot-abuse statistics.
 * Without this test a regression would silently zero out those numbers.
 *
 * Asserts: a rejected submission on a form with the opt-in writes one row per
 * error; the opt-in gate keeps the table empty when disabled; and a valid
 * submission writes nothing (only failures are logged).
 */
final class RecordValidationFailuresTest extends EntropyFormTestCase
{
    private const TABLE = 'tx_form_validation_log';
    private const ENTROPY_ERROR_CODE = 1717686001;

    #[Test]
    public function rejectedSubmissionWithOptInIsLogged(): void
    {
        $this->validateSubmission(['message' => 'vOYhcWlrcTafTMSelBkM'], recordValidationFailures: true);

        $rows = $this->readLog();
        self::assertNotEmpty($rows, 'A rejected submission with the opt-in must write at least one row.');

        $entropyRows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => (int)$row['error_code'] === self::ENTROPY_ERROR_CODE,
        ));
        self::assertCount(1, $entropyRows, 'Exactly one EntropySpam row expected.');
        self::assertSame(EntropyContactForm::IDENTIFIER, $entropyRows[0]['form_identifier']);
        self::assertSame(EntropyContactForm::ERROR_MESSAGE, $entropyRows[0]['error_message']);
    }

    #[Test]
    public function optInDisabledWritesNoRow(): void
    {
        // Same rejected payload, but the form does not opt in — the gate must
        // keep the table empty.
        $this->validateSubmission(['message' => 'vOYhcWlrcTafTMSelBkM'], recordValidationFailures: false);

        self::assertSame([], $this->readLog(), 'No row may be written when the opt-in is off.');
    }

    #[Test]
    public function acceptedSubmissionWritesNoRow(): void
    {
        // A legitimate submission produces no errors, so nothing is logged even
        // with the opt-in enabled.
        $human = SpamCorpus::humanSubmissions()['normal contact'][0];
        $this->validateSubmission($human, recordValidationFailures: true);

        self::assertSame([], $this->readLog(), 'Only validation failures are logged, never successful submissions.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readLog(): array
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE)
            ->select(['*'], self::TABLE)
            ->fetchAllAssociative();
    }
}
