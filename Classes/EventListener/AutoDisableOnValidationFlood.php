<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\EventListener;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Repository\ValidationLogRepository;
use TYPO3\CMS\Form\Event\AfterFormIsValidatedEvent;
use TYPO3\CMS\Form\Service\FormKillSwitch;

/**
 * Switches a flooded form off by itself. Opt-in via the extension
 * configuration flag `featureFormAutoDisable`; off, nothing here runs.
 *
 * Why it is off by default: this automation takes a feature of the site away
 * from real visitors. That is the right trade during an attack and the wrong
 * one as a surprise, so somebody has to decide it once, knowingly.
 *
 * Why every block it sets is time-limited: an automatic shutdown without an
 * automatic release is strictly worse than the manual switch — it fails
 * closed on a weekend and nobody notices until Monday, by which time the
 * attack is long over and the contact form has been dead for two days. The
 * block expires on its own (FormKillSwitch evaluates `until` on read, no cron
 * involved); if the flood is still running when it does, the next wave trips
 * the threshold again within minutes.
 *
 * It only ever disables the ONE form that is being hit — never a site, never
 * globally. An automatism that can take down every form of an installation is
 * not a safety net, it is a denial-of-service amplifier.
 *
 * The count comes from tx_form_validation_log, which is why the feature flag
 * also turns that log on for forms that did not opt in (see
 * RecordValidationFailures). No new table, no second counter that could
 * disagree with the statistics view, and no privacy cost — the validation log
 * stores no submitted values.
 *
 * Runs after the logger so the submission that crosses the threshold is itself
 * already counted; otherwise the trigger would always lag by one request.
 */
#[AsEventListener(
    identifier: 'wapplersystems-form/auto-disable-on-validation-flood',
    after: 'wapplersystems-form/record-validation-failures',
)]
final class AutoDisableOnValidationFlood implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const TRIGGERED_BY = 'auto';

    public function __construct(
        private readonly FormKillSwitch $killSwitch,
        private readonly ValidationLogRepository $validationLogRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Context $context,
    ) {}

    public function __invoke(AfterFormIsValidatedEvent $event): void
    {
        if (!$event->result->hasErrors() || !$this->featureEnabled()) {
            return;
        }

        $request = $event->request;
        if (!is_int($request->getAttribute('applicationType'))
            || !ApplicationType::fromRequest($request)->isFrontend()
        ) {
            return;
        }

        // An editor testing a form must not be able to shoot it down.
        if ($this->context->hasAspect('backend.user')
            && $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn', false) === true
        ) {
            return;
        }

        $formIdentifier = $event->formRuntime->getFormDefinition()->getIdentifier();
        if ($formIdentifier === '' || $this->killSwitch->isDisabled($formIdentifier)) {
            // Already blocked — do not extend the block on every further hit,
            // which would make a long-running attack an indefinite outage.
            return;
        }

        $settings = $this->killSwitch->getAutoDisableSettings();
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $failures = $this->validationLogRepository->countFailuresSince(
            $formIdentifier,
            $now - $settings['windowMinutes'] * 60
        );

        if ($failures < $settings['threshold']) {
            return;
        }

        $until = $now + $settings['durationMinutes'] * 60;
        $this->killSwitch->disable(
            FormKillSwitch::SCOPE_FORM,
            $formIdentifier,
            self::TRIGGERED_BY,
            '',
            $until
        );

        $reason = sprintf(
            'Form "%s" was disabled automatically: %d rejected validations in the last %d minute(s) '
                . '(threshold %d). The block expires at %s.',
            $formIdentifier,
            $failures,
            $settings['windowMinutes'],
            $settings['threshold'],
            date('c', $until)
        );

        // Always logged, even without a notification address — an automatic
        // action nobody can reconstruct afterwards is not something to ship.
        $this->logger?->warning($reason);

        $this->notify($settings['notify'], $formIdentifier, $reason);
    }

    private function notify(string $recipient, string $formIdentifier, string $reason): void
    {
        if ($recipient === '') {
            return;
        }

        try {
            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail->to($recipient)
                ->subject('[TYPO3] Form "' . $formIdentifier . '" was disabled automatically')
                ->text(
                    $reason . "\n\n"
                    . "Undo or extend it in the backend under Administration > Form log > Block forms,\n"
                    . 'or from the command line with: form:enable ' . $formIdentifier . "\n"
                );
            $mail->send();
        } catch (\Throwable $e) {
            // A broken mail setup must not turn into a frontend error on top of
            // the attack that is already running.
            $this->logger?->error(
                'Could not send the notification for the automatic shutdown of form "' . $formIdentifier . '".',
                ['exception' => $e]
            );
        }
    }

    private function featureEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', 'featureFormAutoDisable');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }
    }
}
