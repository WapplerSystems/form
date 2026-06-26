<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Event;

use TYPO3\CMS\Core\Resource\File;

/**
 * Dispatched from UploadedFileReferenceConverter::importUploadedResource() right
 * after an uploaded file has been stored in FAL (and before the FileReference is
 * built).
 *
 * Use-cases: virus scanning, EXIF/metadata stripping, custom storage policy,
 * content-based blocking. The `$file` is the persisted FAL file and may be
 * inspected or post-processed in place. A listener that throws (e.g. on a virus
 * hit) aborts the property mapping and thereby rejects the upload.
 */
final class AfterFileUploadedEvent
{
    /**
     * @param array<string, mixed> $uploadInfo the raw PHP upload info (name, type, size, tmp_name, …)
     */
    public function __construct(
        public readonly File $file,
        public readonly array $uploadInfo,
    ) {}
}
