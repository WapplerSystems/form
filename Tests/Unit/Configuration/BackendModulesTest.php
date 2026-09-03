<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Guards the shape of Configuration/Backend/Modules.php.
 *
 * This branch shipped v14's spelling `'labels' => 'form.modules.form_log'` — a
 * label *domain*, which 13.4 has no concept of. BaseModule::createFromArray()
 * accepts an array of label references or an `LLL:` file whose keys are
 * `mlang_tabs_tab` and friends, and silently falls through for anything else:
 * the modules appeared in the menu with an empty title, and nothing anywhere
 * said why. A structural test is the only cheap way to catch that, because
 * every value is a perfectly valid string.
 *
 * Also checks that each referenced label actually exists in the XLIFF it names.
 * A correct reference to a key nobody wrote produces the same empty title.
 */
final class BackendModulesTest extends UnitTestCase
{
    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function moduleProvider(): array
    {
        $modules = require dirname(__DIR__, 3) . '/Configuration/Backend/Modules.php';
        $cases = [];
        foreach ($modules as $identifier => $configuration) {
            $cases[$identifier] = [$identifier, $configuration];
        }
        return $cases;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[Test]
    #[DataProvider('moduleProvider')]
    public function labelsAreResolvableOnThisBranch(string $identifier, array $configuration): void
    {
        $labels = $configuration['labels'] ?? null;

        if (is_string($labels)) {
            self::assertStringStartsWith(
                'LLL:',
                $labels,
                sprintf(
                    'Module "%s" declares labels as the bare string "%s". On 13.4 that is neither an'
                    . ' array of references nor an LLL: file, so the module title renders empty.',
                    $identifier,
                    $labels,
                ),
            );
            return;
        }

        self::assertIsArray($labels, sprintf('Module "%s" has no usable labels.', $identifier));
        foreach (['title', 'description', 'shortDescription'] as $key) {
            self::assertArrayHasKey(
                $key,
                $labels,
                sprintf('Module "%s" is missing the "%s" label.', $identifier, $key),
            );
            self::assertStringStartsWith('LLL:', (string)$labels[$key]);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[Test]
    #[DataProvider('moduleProvider')]
    public function everyReferencedLabelExists(string $identifier, array $configuration): void
    {
        $labels = $configuration['labels'] ?? null;
        if (!is_array($labels)) {
            self::markTestSkipped('Only the array form names individual keys.');
        }

        foreach ($labels as $key => $reference) {
            [$file, $labelKey] = self::splitReference((string)$reference);
            self::assertFileExists(
                $file,
                sprintf('Module "%s", label "%s": %s does not exist.', $identifier, $key, $file),
            );
            self::assertStringContainsString(
                'id="' . $labelKey . '"',
                (string)file_get_contents($file),
                sprintf(
                    'Module "%s", label "%s": %s has no trans-unit "%s".',
                    $identifier,
                    $key,
                    basename($file),
                    $labelKey,
                ),
            );
        }
    }

    /**
     * @return array{string, string} Absolute path, label key
     */
    private static function splitReference(string $reference): array
    {
        $path = substr($reference, strlen('LLL:EXT:form/'));
        $position = strrpos($path, ':');
        self::assertNotFalse($position, sprintf('Reference "%s" names no label key.', $reference));

        return [
            dirname(__DIR__, 3) . '/' . substr($path, 0, $position),
            substr($path, $position + 1),
        ];
    }
}
