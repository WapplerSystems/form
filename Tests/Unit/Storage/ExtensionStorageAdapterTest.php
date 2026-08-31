<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Form\Domain\Configuration\PersistenceConfigurationService;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface as ExtFormConfigurationManagerInterface;
use TYPO3\CMS\Form\Mvc\Configuration\YamlSource;
use TYPO3\CMS\Form\Storage\ExtensionStorageAdapter;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExtensionStorageAdapterTest extends UnitTestCase
{
    /**
     * PersistenceConfigurationService is final, so it is built for real on top of
     * two stubbed configuration managers rather than doubled.
     */
    private function buildSubject(bool $allowSaveToExtensionPaths): ExtensionStorageAdapter
    {
        $extbaseConfigurationManager = $this->createStub(ExtbaseConfigurationManagerInterface::class);
        $extbaseConfigurationManager->method('getConfiguration')->willReturn([]);
        $extFormConfigurationManager = $this->createStub(ExtFormConfigurationManagerInterface::class);
        $extFormConfigurationManager->method('getYamlConfiguration')->willReturn([
            'persistenceManager' => [
                'allowSaveToExtensionPaths' => $allowSaveToExtensionPaths,
            ],
        ]);
        $storageConfiguration = new PersistenceConfigurationService(
            $extbaseConfigurationManager,
            $extFormConfigurationManager,
        );

        return new ExtensionStorageAdapter(
            $this->createStub(YamlSource::class),
            $this->createStub(ResourceFactory::class),
            $storageConfiguration,
            $this->createStub(FrontendInterface::class),
        );
    }

    /**
     * The default: a form shipped inside an extension is versioned with that
     * extension, so the form editor opens it for viewing rather than editing.
     */
    #[Test]
    public function formsInExtensionPathsAreReadOnly(): void
    {
        $subject = $this->buildSubject(false);

        self::assertTrue($subject->isReadOnly('EXT:my_extension/Resources/Private/Forms/contact.form.yaml'));
    }

    #[Test]
    public function extensionPathsAreWritableWhereTheIntegratorAllowedIt(): void
    {
        $subject = $this->buildSubject(true);

        self::assertFalse($subject->isReadOnly('EXT:my_extension/Resources/Private/Forms/contact.form.yaml'));
    }

    /**
     * Guards against the two answers drifting apart: the flag findAll() puts on
     * every listed form and the one isReadOnly() gives for a single identifier
     * are the same condition, and the editor trusts the second one.
     */
    #[Test]
    public function theSingleFormAnswerMatchesTheListedMetadata(): void
    {
        foreach ([true, false] as $allowed) {
            $subject = $this->buildSubject($allowed);

            self::assertSame(
                !$allowed,
                $subject->isReadOnly('EXT:my_extension/Resources/Private/Forms/contact.form.yaml'),
                sprintf('allowSaveToExtensionPaths=%s', var_export($allowed, true)),
            );
        }
    }
}
