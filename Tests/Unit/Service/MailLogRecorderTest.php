<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Service;

use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Enum\MailLogStatus;
use TYPO3\CMS\Form\Service\MailLogRecipientFormatter;
use TYPO3\CMS\Form\Service\MailLogRecorder;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MailLogRecorderTest extends UnitTestCase
{
    /** @var list<array{string, mixed, mixed}> */
    private array $writes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->writes = [];
    }

    private function recorder(
        bool $masterSwitch = true,
        bool $allForms = true,
        int $insertUid = 42,
        ?\Throwable $insertThrows = null,
    ): MailLogRecorder {
        $repository = $this->createStub(MailLogRepository::class);
        $repository->method('open')->willReturnCallback(
            function (array $row) use ($insertUid, $insertThrows): int {
                if ($insertThrows !== null) {
                    throw $insertThrows;
                }
                $this->writes[] = ['open', null, $row];
                return $insertUid;
            }
        );
        $repository->method('update')->willReturnCallback(
            function (int $uid, array $fields): void {
                $this->writes[] = ['update', $uid, $fields];
            }
        );
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnCallback(
            static fn(string $ext, string $key): mixed => match ($key) {
                'featureMailLog' => $masterSwitch ? '1' : '0',
                'mailLogAllForms' => $allForms ? '1' : '0',
                default => '0',
            }
        );

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('getRealTransport')->willReturn(new NullTransport());
        $mailer->method('getSentMessage')->willReturn(null);

        return new MailLogRecorder(
            $repository,
            new MailLogRecipientFormatter(new HashService()),
            $extensionConfiguration,
            $mailer,
        );
    }

    /**
     * @param array<string, mixed> $renderingOptions
     */
    private function context(array $renderingOptions = []): FinisherContext
    {
        $formDefinition = $this->createStub(FormDefinition::class);
        $formDefinition->method('getIdentifier')->willReturn('contact');
        $formDefinition->method('getRenderingOptions')->willReturn($renderingOptions);

        $request = $this->createStub(Request::class);
        $request->method('getAttribute')->willReturn(null);

        $formRuntime = $this->createStub(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);

        $context = $this->createStub(FinisherContext::class);
        $context->method('getFormRuntime')->willReturn($formRuntime);
        $context->method('getRequest')->willReturn($request);

        return $context;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function finisher(array $options = []): EmailFinisher
    {
        $finisher = $this->createStub(EmailFinisher::class);
        $finisher->method('getOptions')->willReturn($options);
        $finisher->method('getFinisherIdentifier')->willReturn('EmailToReceiver');

        return $finisher;
    }

    /**
     * A stub rather than a real FluidEmail: its constructor resolves
     * ViewFactoryInterface through the container, which a unit test has none of.
     */
    private function mail(): FluidEmail
    {
        $mail = $this->createStub(FluidEmail::class);
        $mail->method('getTo')->willReturn([new Address('info@example.com')]);
        $mail->method('getFrom')->willReturn([new Address('noreply@example.com')]);
        $mail->method('getReplyTo')->willReturn([new Address('erika@example.com')]);
        $mail->method('getSubject')->willReturn('Neue Kontaktanfrage von Erika');
        $mail->method('getAttachments')->willReturn([]);

        return $mail;
    }

    #[Test]
    public function theHappyPathOpensPreparesAndSends(): void
    {
        $recorder = $this->recorder();
        $finisher = $this->finisher();
        $context = $this->context(['mailLog' => ['enable' => true, 'recipients' => 'full', 'subject' => true]]);

        $recorder->open($finisher, $context);
        $recorder->prepare($this->mail(), $finisher);
        $recorder->sent($finisher);

        self::assertSame(['open', 'update', 'update'], array_column($this->writes, 0));

        [, , $opened] = $this->writes[0];
        self::assertSame(MailLogStatus::PENDING->value, $opened['status']);
        self::assertSame('contact', $opened['form_identifier']);
        self::assertSame('EmailToReceiver', $opened['finisher_identifier']);
        self::assertNotSame('', $opened['submission_id']);

        [, $uid, $prepared] = $this->writes[1];
        self::assertSame(42, $uid);
        self::assertSame(MailLogStatus::PREPARED->value, $prepared['status']);
        self::assertSame('info@example.com', $prepared['recipients']);
        self::assertSame(1, $prepared['recipient_count']);
        self::assertSame('Neue Kontaktanfrage von Erika', $prepared['subject']);
        self::assertSame('NullTransport', $prepared['transport']);

        [, , $sentFields] = $this->writes[2];
        self::assertSame(MailLogStatus::SENT->value, $sentFields['status']);
        self::assertGreaterThan(0, $sentFields['tstamp']);
    }

    #[Test]
    public function aConfigurationFailureBeforeTheMailExistsStillProducesARow(): void
    {
        // The production case: senderAddress missing, so the exception is thrown
        // during option validation — before MailBeforeSendingEvent could fire.
        // open() has run, prepare() never will.
        $recorder = $this->recorder();
        $finisher = $this->finisher();
        $context = $this->context();

        $recorder->open($finisher, $context);
        $recorder->failed(
            $finisher,
            $context,
            new FinisherException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210)
        );

        self::assertSame(['open', 'update'], array_column($this->writes, 0));

        [, , $failed] = $this->writes[1];
        self::assertSame(MailLogStatus::FAILED->value, $failed['status']);
        self::assertSame(1327060210, $failed['error_code']);
        self::assertStringContainsString('senderAddress', $failed['error_message']);
    }

    #[Test]
    public function aFailureWithoutAnOpenRowWritesAStandaloneRow(): void
    {
        // A failure with no record is the thing this feature exists to prevent,
        // so it must not depend on open() having run.
        $recorder = $this->recorder();
        $finisher = $this->finisher();

        $recorder->failed(
            $finisher,
            $this->context(),
            new FinisherException('nope', 1327060200)
        );

        self::assertSame(['open'], array_column($this->writes, 0));
        [, , $row] = $this->writes[0];
        self::assertSame(MailLogStatus::FAILED->value, $row['status']);
        self::assertSame(1327060200, $row['error_code']);
    }

    #[Test]
    public function transportErrorTextIsWithheldWithoutAnOptIn(): void
    {
        // An SMTP rejection quotes the recipient address.
        $recorder = $this->recorder();
        $finisher = $this->finisher();
        $context = $this->context();

        $recorder->open($finisher, $context);
        $recorder->failed(
            $finisher,
            $context,
            new FinisherException('Failed to send the email: 550 <erika@example.com> unknown', 1754047320)
        );

        [, , $failed] = $this->writes[1];
        self::assertSame('', $failed['error_message']);
        self::assertSame(1754047320, $failed['error_code'], 'the code must survive so the failure is still classifiable');
    }

    #[Test]
    public function withoutAnOptInNoIdentifyingDataIsWritten(): void
    {
        $recorder = $this->recorder();
        $finisher = $this->finisher();

        $recorder->open($finisher, $this->context());
        $recorder->prepare($this->mail(), $finisher);

        [, , $opened] = $this->writes[0];
        self::assertSame('none', $opened['recipient_mode']);

        [, , $prepared] = $this->writes[1];
        self::assertSame('', $prepared['recipients']);
        self::assertSame(1, $prepared['recipient_count'], 'the count is not personal data and stays truthful');
        self::assertArrayNotHasKey('subject', $prepared);
        self::assertArrayNotHasKey('sender', $prepared);
        self::assertArrayNotHasKey('reply_to', $prepared);
    }

    #[Test]
    public function aDisabledPolicyWritesNothingAtAll(): void
    {
        $recorder = $this->recorder(masterSwitch: false);
        $finisher = $this->finisher();

        $recorder->open($finisher, $this->context(['mailLog' => ['enable' => true]]));
        $recorder->prepare($this->mail(), $finisher);
        $recorder->sent($finisher);

        self::assertSame([], $this->writes);
    }

    #[Test]
    public function twoFinishersInOneChainGetIndependentRows(): void
    {
        $recorder = $this->recorder();
        $toReceiver = $this->finisher();
        $toSender = $this->finisher();
        $context = $this->context(['mailLog' => ['enable' => true]]);

        $recorder->open($toReceiver, $context);
        $recorder->open($toSender, $context);
        $recorder->sent($toReceiver);
        $recorder->sent($toSender);

        self::assertSame(['open', 'open', 'update', 'update'], array_column($this->writes, 0));
        self::assertSame(
            $this->writes[0][2]['submission_id'],
            $this->writes[1][2]['submission_id'],
            'both mails belong to the same submission'
        );
    }

    #[Test]
    public function prepareAndSentAreNoOpsWhenNoRowWasOpened(): void
    {
        // A row can be missing because the policy declined or because the insert
        // failed. Neither may turn into an update against a random uid.
        $recorder = $this->recorder();
        $finisher = $this->finisher();

        $recorder->prepare($this->mail(), $finisher);
        $recorder->sent($finisher);

        self::assertSame([], $this->writes);
    }

    #[Test]
    public function aMissingTableDisablesTheRecorderInsteadOfBreakingTheSubmission(): void
    {
        // Happens between deploying code and applying the schema. Losing a log
        // row is always preferable to losing an inquiry.
        $recorder = $this->recorder(insertThrows: $this->tableNotFound());
        $finisher = $this->finisher();
        $context = $this->context(['mailLog' => ['enable' => true]]);

        $recorder->open($finisher, $context);
        $recorder->prepare($this->mail(), $finisher);
        $recorder->sent($finisher);

        self::assertSame([], $this->writes);
    }

    #[Test]
    public function anOptedInFormRecordsHashedRecipientsWithoutTheAddress(): void
    {
        $recorder = $this->recorder();
        $finisher = $this->finisher(['mailLog' => ['recipients' => 'hashed']]);
        $context = $this->context(['mailLog' => ['enable' => true, 'recipients' => 'full']]);

        $recorder->open($finisher, $context);
        $recorder->prepare($this->mail(), $finisher);

        [, , $prepared] = $this->writes[1];
        self::assertStringNotContainsString('@', $prepared['recipients'], 'the finisher-level override must win');
        self::assertNotSame('', $prepared['recipients']);
    }

    private function tableNotFound(): TableNotFoundException
    {
        return new TableNotFoundException(
            PdoException::new(new \PDOException('no such table')),
            null
        );
    }
}
