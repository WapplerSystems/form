<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\ViewHelpers;

use TYPO3\CMS\Core\Resource\MimeTypeDetector;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Scope: frontend
 *
 * WapplerSystems fork: resolves the list of file extensions that correspond to a
 * FileUpload/ImageUpload element's "properties.allowedMimeTypes", so the frontend
 * can show editors' visitors *which file types they may upload* without anybody
 * having to maintain that list a second time by hand.
 *
 * Rendered only when the element opts in via properties.showAllowedFileExtensions
 * (Inspector checkbox "Show allowed file extensions") - see
 * Configuration/Form/Base/FormElements/FileUpload.yaml.
 *
 * The mapping comes from the Core MimeTypeDetector, i.e. it always matches what
 * the MimeType validator actually accepts. A handful of the MIME types offered by
 * the form editor are non-standard aliases that the Core map does not know; those
 * are covered by self::MIME_TYPE_ALIASES below.
 *
 * Returns an array of lowercase, de-duplicated extensions in the order of the
 * given MIME types - format it in the template, e.g. via "-> f:join(separator: ', ')".
 */
final class AllowedFileExtensionsViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Non-standard MIME types that the form editor offers (FileUpload.yaml /
     * ImageUpload.yaml selectOptions) but Core's MimeTypeDetector does not map.
     * Without these the corresponding entry would silently vanish from the hint.
     *
     * @var array<string, list<string>>
     */
    private const MIME_TYPE_ALIASES = [
        'application/msexcel' => ['xls'],
        'application/mspowerpoint' => ['ppt'],
    ];

    public function initializeArguments(): void
    {
        $this->registerArgument('mimeTypes', 'array', 'The element\'s properties.allowedMimeTypes', false, []);
    }

    /**
     * @return list<string>
     */
    public function render(): array
    {
        $mimeTypes = $this->arguments['mimeTypes'];
        if (!is_array($mimeTypes) || $mimeTypes === []) {
            return [];
        }

        $mimeTypeDetector = new MimeTypeDetector();
        $fileExtensions = [];
        foreach ($mimeTypes as $mimeType) {
            if (!is_string($mimeType) || $mimeType === '') {
                continue;
            }
            $mimeType = strtolower(trim($mimeType));
            $extensions = $mimeTypeDetector->getFileExtensionsForMimeType($mimeType);
            if ($extensions === []) {
                $extensions = self::MIME_TYPE_ALIASES[$mimeType] ?? [];
            }
            foreach ($extensions as $extension) {
                $fileExtensions[strtolower($extension)] = true;
            }
        }

        return array_keys($fileExtensions);
    }
}
