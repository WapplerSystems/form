<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;
use TYPO3\CMS\Form\Domain\Finishers\RedirectFinisher;
use TYPO3\CMS\Form\Event\BeforeFinisherExecutedEvent;
use TYPO3\CMS\Form\Event\FinisherFailedEvent;
use TYPO3\CMS\Form\EventListener\RecordMailDeliveries;
use TYPO3\CMS\Form\Service\MailLogRecorder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The listener itself is four delegations; what is worth testing is the
 * `instanceof` filter, because BeforeFinisherExecutedEvent and
 * FinisherFailedEvent fire for EVERY finisher type. Without the filter a mail
 * log would collect rows for redirects and database writes.
 */
final class RecordMailDeliveriesTest extends UnitTestCase
{
    #[Test]
    public function anEmailFinisherOpensARow(): void
    {
        $recorder = $this->createMock(MailLogRecorder::class);
        $recorder->expects($this->once())->method('open');

        $finisher = $this->createStub(EmailFinisher::class);
        $context = $this->createStub(FinisherContext::class);

        (new RecordMailDeliveries($recorder))->open(new BeforeFinisherExecutedEvent($finisher, $context));
    }

    #[Test]
    public function anotherFinisherTypeIsIgnoredOnOpen(): void
    {
        $recorder = $this->createMock(MailLogRecorder::class);
        $recorder->expects($this->never())->method('open');

        $finisher = $this->createStub(RedirectFinisher::class);
        $context = $this->createStub(FinisherContext::class);

        (new RecordMailDeliveries($recorder))->open(new BeforeFinisherExecutedEvent($finisher, $context));
    }

    #[Test]
    public function anEmailFinisherFailureIsRecorded(): void
    {
        $recorder = $this->createMock(MailLogRecorder::class);
        $recorder->expects($this->once())->method('failed');

        $finisher = $this->createStub(EmailFinisher::class);
        $context = $this->createStub(FinisherContext::class);
        $exception = new FinisherException('nope', 1327060210);

        (new RecordMailDeliveries($recorder))->failed(new FinisherFailedEvent($finisher, $context, $exception));
    }

    #[Test]
    public function anotherFinisherTypeIsIgnoredOnFailure(): void
    {
        // A failing SaveToDatabase or Redirect finisher is a real event, but it
        // is not a mail and must not land in the mail log.
        $recorder = $this->createMock(MailLogRecorder::class);
        $recorder->expects($this->never())->method('failed');

        $finisher = $this->createStub(FinisherInterface::class);
        $context = $this->createStub(FinisherContext::class);
        $exception = new FinisherException('nope', 1234567890);

        (new RecordMailDeliveries($recorder))->failed(new FinisherFailedEvent($finisher, $context, $exception));
    }
}
