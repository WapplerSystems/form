<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Domain\Finishers;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\TemplatedEmailFactory;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Enum\MailLogStatus;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The mail log against the real EmailFinisher, the real AbstractFinisher catch
 * block and a real database.
 *
 * These four cases are the reason the feature is shaped the way it is, and none
 * of them can be covered by a unit test: they depend on *where* inside
 * EmailFinisher an exception is thrown, which decides whether any mail-specific
 * event ever fires.
 */
final class EmailFinisherMailLogTest extends FunctionalTestCase
{
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'form' => [
                'featureMailLog' => '1',
                // Record personal-data-free rows even without a per-form opt-in.
                // The production failure happened on exactly such a form.
                'mailLogAllForms' => '1',
            ],
        ],
    ];

    #[Test]
    public function aSuccessfulSendIsRecordedAsSent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');
        $this->replaceMailer($mailer);

        $this->execute($this->finisher(), $this->options());

        $row = $this->lastRow();
        self::assertSame(MailLogStatus::SENT->value, (int)$row['status']);
        self::assertSame('EmailToReceiver', $row['finisher_identifier']);
        self::assertSame(1, (int)$row['recipient_count']);
        // No opt-in, so the address itself must not be there — but the count must.
        self::assertSame('', $row['recipients']);
        self::assertSame('none', $row['recipient_mode']);
        self::assertSame('', $row['subject']);
    }

    /**
     * The production failure, byte for byte: senderAddress empty, so
     * EmailFinisher throws while validating its options — before it ever builds a
     * mail and therefore before MailBeforeSendingEvent could fire. A log that
     * only wrote rows from that event would have stayed silent while the form
     * failed on every single submission for ten days.
     */
    #[Test]
    public function aMissingSenderAddressIsRecordedAsFailed(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');
        $this->replaceMailer($mailer);

        $options = $this->options();
        unset($options['senderAddress']);
        $this->execute($this->finisher(), $options);

        $row = $this->lastRow();
        self::assertSame(MailLogStatus::FAILED->value, (int)$row['status']);
        self::assertSame(1327060210, (int)$row['error_code']);
        self::assertStringContainsString('senderAddress', $row['error_message']);
    }

    /**
     * A transport failure is reported too, but its text can quote the recipient
     * address, so without an opt-in the code is kept and the message is not.
     */
    #[Test]
    public function aTransportFailureIsRecordedWithoutItsMessage(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(
            new TransportException('550 <john.doe@example.com>: user unknown')
        );
        $this->replaceMailer($mailer);

        $this->execute($this->finisher(), $this->options());

        $row = $this->lastRow();
        self::assertSame(MailLogStatus::FAILED->value, (int)$row['status']);
        self::assertSame(1754047320, (int)$row['error_code']);
        self::assertSame('', $row['error_message'], 'an SMTP rejection can name the recipient');
    }

    /**
     * Not every failure is a FinisherException. A malformed sender address
     * throws RfcComplianceException, and a broken Fluid mail template surfaces
     * inside send() because FluidEmail renders lazily — neither is caught, so no
     * terminal event fires at all.
     *
     * This is what the PENDING row is for: the trace exists, and the module
     * reports it honestly as "outcome unknown" instead of showing nothing.
     */
    #[Test]
    public function anUncaughtThrowableLeavesTheRowPending(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new RfcComplianceException('invalid address'));
        $this->replaceMailer($mailer);

        try {
            $this->execute($this->finisher(), $this->options());
        } catch (RfcComplianceException) {
            // expected: it escapes the finisher entirely
        }

        $row = $this->lastRow();
        self::assertContains(
            (int)$row['status'],
            [MailLogStatus::PENDING->value, MailLogStatus::PREPARED->value],
            'a row that never got an outcome must stay non-terminal rather than vanish'
        );
    }

    #[Test]
    public function nothingIsRecordedWhenTheFeatureIsOff(): void
    {
        $this->get(ExtensionConfiguration::class)->set('form', ['featureMailLog' => '0']);

        $mailer = $this->createMock(MailerInterface::class);
        $this->replaceMailer($mailer);

        $this->execute($this->finisher(), $this->options());

        self::assertNull($this->lastRow());
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'senderAddress' => 'sender@example.org',
            'templateName' => 'template',
            'recipients' => ['user@example.org' => 'John Doe'],
            'subject' => 'a subject',
        ];
    }

    private function finisher(): EmailFinisher
    {
        $finisher = new EmailFinisher(
            $this->get(EventDispatcher::class),
            $this->get(TemplatedEmailFactory::class),
            $this->get(MailerInterface::class),
        );

        $translationService = $this->createMock(TranslationService::class);
        $translationService->method('translateFinisherOption')->willReturnCallback(
            static fn() => func_get_arg(3)
        );
        $finisher->injectTranslationService($translationService);

        // Both are required and both are missing from the sibling
        // EmailFinisherTest: without the dispatcher the fork's finisher events
        // never fire (so nothing would be logged), and without a logger the
        // failure branch dies on $this->logger->error() before it can dispatch.
        $finisher->injectEventDispatcher($this->get(EventDispatcher::class));
        $finisher->setLogger(new NullLogger());
        $finisher->setFinisherIdentifier('EmailToReceiver');

        return $finisher;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function execute(EmailFinisher $finisher, array $options): void
    {
        $finisher->setOptions($options);

        $formDefinition = $this->createMock(FormDefinition::class);
        $formDefinition->method('getIdentifier')->willReturn('contact');
        $formDefinition->method('getRenderingOptions')->willReturn([]);
        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);

        $finisher->execute(new FinisherContext($formRuntime, $this->createMock(Request::class)));
    }

    private function replaceMailer(MailerInterface $mailer): void
    {
        /** @var Container $container */
        $container = $this->get('service_container');
        $container->set(MailerInterface::class, $mailer);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastRow(): ?array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(MailLogRepository::TABLE_NAME);
        $row = $queryBuilder
            ->select('*')
            ->from(MailLogRepository::TABLE_NAME)
            ->orderBy('uid', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }
}
