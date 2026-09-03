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
 * Guards the label references in the backend Fluid templates.
 *
 * The backend templates arrived from release/v14 written against label
 * *domains* — `<f:translate key="form.database:mailLog.headline" />`. 13.4 has
 * no domains, and in an Extbase context f:translate then hands the whole string
 * to LocalizationUtility::translate() with the extension name off the request,
 * which looks for a key literally called "form.database:mailLog.headline" in
 * locallang.xlf, finds nothing and renders an **empty string**. No exception, no
 * log entry: the form log module simply had unlabelled statistics tiles and an
 * empty table header, and the missing text was the only symptom.
 *
 * Two rules therefore, both cheap and both invisible in a diff otherwise:
 * no domain syntax, and every reference resolves to a label that exists.
 */
final class BackendTemplateLabelsTest extends UnitTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function backendTemplateProvider(): array
    {
        $root = dirname(__DIR__, 3) . '/Resources/Private';
        $files = [];
        foreach (['Templates/Backend', 'Partials/Backend'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'html') {
                    $files[$directory . '/' . $file->getFilename()] = [$file->getPathname()];
                }
            }
        }
        ksort($files);
        return $files;
    }

    #[Test]
    #[DataProvider('backendTemplateProvider')]
    public function noTemplateUsesV14LabelDomains(string $path): void
    {
        $contents = (string)file_get_contents($path);

        self::assertDoesNotMatchRegularExpression(
            '/(key|id)\s*[=:]\s*[\'"][a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*:/',
            $contents,
            sprintf(
                '%s references a label domain. On 13.4 that resolves to an empty string rather than'
                . ' an error - use a full LLL:EXT:form/... reference.',
                basename($path),
            ),
        );
    }

    #[Test]
    #[DataProvider('backendTemplateProvider')]
    public function everyLabelReferenceResolves(string $path): void
    {
        $root = dirname(__DIR__, 3);
        preg_match_all(
            '#LLL:EXT:form/(Resources/Private/Language/[A-Za-z0-9_./]+\.xlf):([^"\'\s)}]+)#',
            (string)file_get_contents($path),
            $matches,
            PREG_SET_ORDER,
        );

        if ($matches === []) {
            self::markTestSkipped('No EXT:form label references in this template.');
        }

        foreach ($matches as $match) {
            [, $file, $key] = $match;
            // A key assembled at render time (`…xlf:{label}`) can only be
            // checked where the section is called, not here.
            if (str_contains($key, '{')) {
                continue;
            }
            $languageFile = $root . '/' . $file;
            self::assertFileExists($languageFile, sprintf('%s references %s.', basename($path), $file));
            self::assertStringContainsString(
                'id="' . $key . '"',
                (string)file_get_contents($languageFile),
                sprintf('%s references "%s", which %s does not define.', basename($path), $key, basename($file)),
            );
        }
    }
}
