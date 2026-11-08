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
use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3\CMS\Form\Tests\Functional\Fixtures\Forms\EntropyContactForm;
use TYPO3\CMS\Form\Tests\Functional\Framework\EntropyFormTestCase;

/**
 * The claim the whole feature stands or falls on: a blocked form does not
 * accept a submission.
 *
 * Driven through the real FormRuntime validation chain and the real event
 * dispatcher, because the thing that is easy to get wrong here is not the
 * switch but where it is consulted — FormRuntime::render() hands off to
 * invokeFinishers() before it ever dispatches the rendering event, so a
 * kill switch enforced only on the rendering side would show a notice to
 * visitors while the finishers keep firing for anyone who posts straight at
 * the action URI. This test exercises the submission side on purpose.
 */
final class FormKillSwitchTest extends EntropyFormTestCase
{
    private const BLOCKED_ERROR_CODE = 1757890001;

    /**
     * A submission that is beyond reproach: if this is rejected, it is the
     * switch and not a spam filter.
     *
     * @var array<string, string>
     */
    private const VALID_SUBMISSION = [
        'name' => 'Maria Lindqvist',
        'email' => 'maria@example.com',
        'message' => 'Bitte rufen Sie mich am Montag zurueck, vielen Dank!',
    ];

    #[Test]
    public function aSubmissionIsAcceptedWhileNothingIsBlocked(): void
    {
        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertFalse($result->hasErrors());
    }

    #[Test]
    public function aSubmissionToABlockedFormIsRejected(): void
    {
        $this->killSwitch()->disable(FormKillSwitch::SCOPE_FORM, EntropyContactForm::IDENTIFIER, 'test');

        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertTrue($result->hasErrors());
        self::assertContains(self::BLOCKED_ERROR_CODE, $this->errorCodes($result));
    }

    #[Test]
    public function aSubmissionToAnotherFormIsUnaffected(): void
    {
        $this->killSwitch()->disable(FormKillSwitch::SCOPE_FORM, 'some-other-form', 'test');

        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertFalse($result->hasErrors());
    }

    #[Test]
    public function theGlobalBlockRejectsEverySubmission(): void
    {
        $this->killSwitch()->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'test');

        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertContains(self::BLOCKED_ERROR_CODE, $this->errorCodes($result));
    }

    #[Test]
    public function releasingAFormMakesItAcceptSubmissionsAgain(): void
    {
        $killSwitch = $this->killSwitch();
        $killSwitch->disable(FormKillSwitch::SCOPE_FORM, EntropyContactForm::IDENTIFIER, 'test');
        $killSwitch->enable(FormKillSwitch::SCOPE_FORM, EntropyContactForm::IDENTIFIER);

        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertFalse($result->hasErrors());
    }

    /**
     * The release that nobody has to remember. Without it an automatic block
     * would fail closed over a weekend.
     */
    #[Test]
    public function anExpiredBlockNoLongerRejects(): void
    {
        $this->killSwitch()->disable(
            FormKillSwitch::SCOPE_FORM,
            EntropyContactForm::IDENTIFIER,
            'auto',
            '',
            (int)$GLOBALS['EXEC_TIME'] - 1
        );

        $result = $this->validateSubmission(self::VALID_SUBMISSION);

        self::assertFalse($result->hasErrors());
    }

    private function killSwitch(): FormKillSwitch
    {
        return $this->get(FormKillSwitch::class);
    }
}
