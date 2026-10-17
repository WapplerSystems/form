<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Flattens submitted form values into a JSON-safe, storage ready array for
 * the SaveSubmissionFinisher, so uploads, dates and nested values survive the
 * trip into a JSON column.
 *
 * Conversion rules:
 *  - scalars (string|int|float|bool) and null are passed through unchanged,
 *  - \DateTimeInterface becomes an ISO 8601 string (e.g. 2026-07-11T09:00:00+00:00),
 *  - uploaded files (Extbase/Core FileReference or File) become
 *    {uid, identifier, name} so the reference survives without embedding bytes,
 *  - ObjectStorage / iterables / arrays are mapped recursively,
 *  - any remaining object is cast via __toString() when possible, else its
 *    class name is stored as a last resort.
 *
 * @internal
 */
final class SubmissionValueSerializer
{
    /**
     * @param array<string, mixed> $formValues
     * @param bool $includeFiles when false, uploaded-file values normalize to null instead of a file descriptor
     * @return array<string, mixed> JSON-safe representation
     */
    public function serializeValues(array $formValues, bool $includeFiles = true): array
    {
        $result = [];
        foreach ($formValues as $identifier => $value) {
            $result[$identifier] = $this->normalize($value, $includeFiles);
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalize($value, bool $includeFiles = true)
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof ExtbaseFileReference) {
            if (!$includeFiles) {
                return null;
            }
            $original = $value->getOriginalResource();
            return $original instanceof CoreFileReference ? $this->serializeFile($original) : null;
        }

        if ($value instanceof CoreFileReference) {
            return $includeFiles ? $this->serializeFile($value) : null;
        }

        if ($value instanceof FileInterface) {
            if (!$includeFiles) {
                return null;
            }
            return [
                // getUid() is not part of FileInterface, but every concrete
                // upload (File, ProcessedFile) provides it — guard defensively.
                'uid' => method_exists($value, 'getUid') ? (int)$value->getUid() : 0,
                'identifier' => $value->getIdentifier(),
                'name' => $value->getName(),
            ];
        }

        if ($value instanceof ObjectStorage || is_iterable($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = $this->normalize($item, $includeFiles);
            }
            return $items;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }
            return '[' . $value::class . ']';
        }

        return $value;
    }

    /**
     * @return array{uid: int, identifier: string, name: string}
     */
    private function serializeFile(CoreFileReference $reference): array
    {
        $file = $reference->getOriginalFile();
        return [
            'uid' => $file->getUid(),
            'identifier' => $file->getIdentifier(),
            'name' => $reference->getName(),
        ];
    }
}
