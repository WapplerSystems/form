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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Form\Service\FormKillSwitch;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormKillSwitchTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @var array<string, mixed>
     */
    private array $stored = [];

    private FormKillSwitch $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['EXEC_TIME'] = 1_000_000;

        $this->subject = $this->freshInstance();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['EXEC_TIME']);
        parent::tearDown();
    }

    #[Test]
    public function nothingIsBlockedByDefault(): void
    {
        self::assertFalse($this->subject->isDisabled('contact', 'camino'));
    }

    #[Test]
    public function aBlockedFormIsBlocked(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'claude');

        self::assertTrue($this->subject->isDisabled('contact'));
        self::assertFalse($this->subject->isDisabled('newsletter'));
    }

    /**
     * The coarse level has to win. During an incident "block everything" must
     * not be undone by the absence of a narrow entry.
     */
    #[Test]
    public function theGlobalBlockCoversEveryForm(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'claude');

        self::assertTrue($this->subject->isDisabled('contact'));
        self::assertTrue($this->subject->isDisabled('anything-at-all', 'camino'));
    }

    #[Test]
    public function aBlockedSiteCoversOnlyThatSite(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_SITE, 'camino', 'claude');

        self::assertTrue($this->subject->isDisabled('contact', 'camino'));
        self::assertFalse($this->subject->isDisabled('contact', 'other'));
        self::assertFalse($this->subject->isDisabled('contact'));
    }

    #[Test]
    public function releasingOneFormLeavesTheGlobalBlockInPlace(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'claude');
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'claude');

        $this->subject->enable(FormKillSwitch::SCOPE_FORM, 'contact');

        self::assertTrue($this->subject->isDisabled('contact'));
    }

    /**
     * The part that makes a timed block safe: it ends without anyone acting,
     * and without a scheduler having to run.
     */
    #[Test]
    public function aTimedBlockStopsBlockingWhenItExpires(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'auto', '', $GLOBALS['EXEC_TIME'] + 600);
        self::assertTrue($this->subject->isDisabled('contact'));

        $GLOBALS['EXEC_TIME'] += 601;
        $subject = $this->freshInstance();

        self::assertFalse($subject->isDisabled('contact'));
        self::assertSame([], $subject->getState()['forms']);
    }

    #[Test]
    public function anExpiredBlockIsPrunedOnTheNextWrite(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'auto', '', $GLOBALS['EXEC_TIME'] + 600);

        $GLOBALS['EXEC_TIME'] += 601;
        $subject = $this->freshInstance();
        $subject->disable(FormKillSwitch::SCOPE_FORM, 'newsletter', 'claude');

        self::assertArrayNotHasKey('contact', $this->stored[FormKillSwitch::REGISTRY_KEY]['forms']);
    }

    /**
     * Most specific wins, and only from a level that is actually blocking —
     * a wording left behind on an expired entry must not leak into the notice.
     */
    #[Test]
    public function theMessageComesFromTheMostSpecificBlockingLevel(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'claude', 'everything is off');
        $this->subject->disable(FormKillSwitch::SCOPE_SITE, 'camino', 'claude', 'this site is off');
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'claude', 'this form is off');

        self::assertSame('this form is off', $this->subject->getMessage('contact', 'camino'));
        self::assertSame('this site is off', $this->subject->getMessage('newsletter', 'camino'));
        self::assertSame('everything is off', $this->subject->getMessage('newsletter', 'elsewhere'));
    }

    #[Test]
    public function anEmptyMessageFallsThroughToTheNextLevel(): void
    {
        $this->subject->disable(FormKillSwitch::SCOPE_GLOBAL, null, 'claude', 'everything is off');
        $this->subject->disable(FormKillSwitch::SCOPE_FORM, 'contact', 'claude');

        self::assertSame('everything is off', $this->subject->getMessage('contact'));
    }

    #[Test]
    public function withoutAnyBlockThereIsNoMessage(): void
    {
        self::assertSame('', $this->subject->getMessage('contact', 'camino'));
    }

    #[Test]
    public function autoDisableSettingsFallBackToTheDefaults(): void
    {
        self::assertSame(FormKillSwitch::AUTO_DISABLE_DEFAULTS, $this->subject->getAutoDisableSettings());
    }

    #[Test]
    public function autoDisableSettingsAreClampedToSaneValues(): void
    {
        $this->subject->setAutoDisableSettings(0, -5, 0, '  ops@example.org ');

        self::assertSame(
            ['threshold' => 1, 'windowMinutes' => 1, 'durationMinutes' => 1, 'notify' => 'ops@example.org'],
            $this->subject->getAutoDisableSettings()
        );
    }

    /**
     * Cache tags have a restricted character set while a form identifier is
     * whatever an editor typed.
     */
    #[Test]
    #[DataProvider('cacheTagProvider')]
    public function cacheTagsAreSanitised(string $identifier, string $expected): void
    {
        self::assertSame($expected, FormKillSwitch::cacheTagForForm($identifier));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cacheTagProvider(): array
    {
        return [
            'plain' => ['contact', 'tx_form_contact'],
            'dashes kept' => ['job-application', 'tx_form_job-application'],
            'dots replaced' => ['my.form', 'tx_form_my_form'],
            'a run of rejected characters collapses into one underscore' => ['grüße', 'tx_form_gr_e'],
            'spaces collapse too' => ['my  form', 'tx_form_my_form'],
        ];
    }

    /**
     * An instance over the shared in-memory store. Used for a SECOND instance
     * in the expiry tests too: the service keeps a request-local copy of the
     * registry entry, and that copy must not hide from the test what the next
     * request would actually see.
     */
    private function freshInstance(): FormKillSwitch
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('get')->willReturnCallback(
            fn(string $namespace, string $key, mixed $default = null): mixed => $this->stored[$key] ?? $default
        );
        $registry->method('set')->willReturnCallback(
            function (string $namespace, string $key, mixed $value): void {
                $this->stored[$key] = $value;
            }
        );

        return new FormKillSwitch($registry, $this->createStub(CacheManager::class));
    }
}
