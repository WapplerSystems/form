<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\KillSwitch;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Repository\ValidationLogRepository;
use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3\CMS\Form\Tests\Functional\Fixtures\Forms\EntropyContactForm;
use TYPO3\CMS\Form\Tests\Functional\Framework\EntropyFormTestCase;

/**
 * The automatic shutdown, end to end against the real validation log.
 *
 * This is the piece that can take a working form away from real visitors
 * without anyone asking it to, so most of what is asserted here is the cases in
 * which it must NOT fire, plus the expiry that makes firing survivable.
 */
final class AutoDisableOnValidationFloodTest extends EntropyFormTestCase
{
    /**
     * A submission the fixture's EntropySpam validator rejects — i.e. exactly
     * the kind of traffic the automatic shutdown exists for. The fixture has no
     * mandatory fields on purpose, so an empty submission would pass and the
     * listener would never see a trigger.
     *
     * @var array<string, string>
     */
    private const FAILING_SUBMISSION = ['message' => 'vOYhcWlrcTafTMSelBkM'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['form']['featureFormAutoDisable'] = 1;
        $this->get(FormKillSwitch::class)->setAutoDisableSettings(3, 5, 30, '');
    }

    #[Test]
    public function aFormIsDisabledOnceTheThresholdIsCrossed(): void
    {
        $this->seedFailures(EntropyContactForm::IDENTIFIER, 3);

        $this->validateSubmission(self::FAILING_SUBMISSION);

        self::assertTrue($this->get(FormKillSwitch::class)->isDisabled(EntropyContactForm::IDENTIFIER));
    }

    #[Test]
    public function aFormBelowTheThresholdKeepsRunning(): void
    {
        $this->validateSubmission(self::FAILING_SUBMISSION);

        self::assertFalse($this->get(FormKillSwitch::class)->isDisabled(EntropyContactForm::IDENTIFIER));
    }

    /**
     * Rows of a different form must not count, or one flooded form would take
     * down every other form on the installation in turn — which is the failure
     * mode that makes automatic shutdowns a bad idea when done carelessly.
     */
    #[Test]
    public function failuresOfAnotherFormDoNotCount(): void
    {
        $this->seedFailures('some-other-form', 50);

        $this->validateSubmission(self::FAILING_SUBMISSION);

        self::assertFalse($this->get(FormKillSwitch::class)->isDisabled(EntropyContactForm::IDENTIFIER));
    }

    #[Test]
    public function failuresOutsideTheWindowDoNotCount(): void
    {
        $this->seedFailures(EntropyContactForm::IDENTIFIER, 50, 3600);

        $this->validateSubmission(self::FAILING_SUBMISSION);

        self::assertFalse($this->get(FormKillSwitch::class)->isDisabled(EntropyContactForm::IDENTIFIER));
    }

    #[Test]
    public function nothingHappensWhileTheFeatureIsOff(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['form']['featureFormAutoDisable'] = 0;
        $this->seedFailures(EntropyContactForm::IDENTIFIER, 50);

        $this->validateSubmission(self::FAILING_SUBMISSION);

        self::assertFalse($this->get(FormKillSwitch::class)->isDisabled(EntropyContactForm::IDENTIFIER));
    }

    /**
     * The block has to end by itself. An automatic shutdown only a human can
     * undo fails closed on a Friday evening and is still closed on Monday.
     */
    #[Test]
    public function theAutomaticBlockCarriesAnExpiry(): void
    {
        $this->seedFailures(EntropyContactForm::IDENTIFIER, 3);
        $this->validateSubmission(self::FAILING_SUBMISSION);

        $entry = $this->get(FormKillSwitch::class)->getState()['forms'][EntropyContactForm::IDENTIFIER] ?? null;

        self::assertIsArray($entry);
        self::assertSame('auto', $entry['by']);
        self::assertIsInt($entry['until']);
        self::assertGreaterThan((int)$GLOBALS['EXEC_TIME'], $entry['until']);
    }

    /**
     * With the feature on, the validation log records for every form — the
     * counter has no data otherwise, and the form nobody opted in for is
     * exactly the one nobody is watching.
     */
    #[Test]
    public function theValidationLogRecordsEvenWithoutTheFormOptingIn(): void
    {
        $this->validateSubmission(self::FAILING_SUBMISSION, false);

        self::assertGreaterThan(
            0,
            $this->get(ValidationLogRepository::class)
                ->countFailuresSince(EntropyContactForm::IDENTIFIER, (int)$GLOBALS['EXEC_TIME'] - 600)
        );
    }

    private function seedFailures(string $formIdentifier, int $count, int $ageSeconds = 10): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(ValidationLogRepository::TABLE_NAME);

        for ($i = 0; $i < $count; $i++) {
            $connection->insert(ValidationLogRepository::TABLE_NAME, [
                'crdate' => (int)$GLOBALS['EXEC_TIME'] - $ageSeconds,
                'form_identifier' => $formIdentifier,
                'element_identifier' => 'message',
                'property_path' => $formIdentifier . '.message',
                'error_code' => 1221560910,
                'error_message' => 'This field is mandatory.',
                'session_hash' => hash('sha256', 'session-' . $i),
            ]);
        }
    }
}
