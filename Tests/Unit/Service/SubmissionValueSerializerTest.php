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

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Form\Service\SubmissionValueSerializer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class SubmissionValueSerializerTest extends UnitTestCase
{
    #[Test]
    public function scalarsAndNullPassThroughUnchanged(): void
    {
        $serializer = new SubmissionValueSerializer();
        $result = $serializer->serializeValues([
            'name' => 'Erika',
            'age' => 42,
            'agreed' => true,
            'rating' => 3.5,
            'empty' => null,
        ]);

        self::assertSame([
            'name' => 'Erika',
            'age' => 42,
            'agreed' => true,
            'rating' => 3.5,
            'empty' => null,
        ], $result);
    }

    #[Test]
    public function dateTimeIsSerializedAsIso8601(): void
    {
        $serializer = new SubmissionValueSerializer();
        $date = new \DateTimeImmutable('2026-07-11T09:00:00+00:00');

        $result = $serializer->serializeValues(['when' => $date]);

        self::assertSame('2026-07-11T09:00:00+00:00', $result['when']);
    }

    #[Test]
    public function nestedArraysAreRecursivelyNormalized(): void
    {
        $serializer = new SubmissionValueSerializer();
        $result = $serializer->serializeValues([
            'interests' => ['php', 'typo3', 42],
        ]);

        self::assertSame(['php', 'typo3', 42], $result['interests']);
    }

    #[Test]
    public function fileIsSerializedToDescriptorWhenIncluded(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(17);
        $file->method('getIdentifier')->willReturn('/user_upload/cv.pdf');
        $file->method('getName')->willReturn('cv.pdf');

        $serializer = new SubmissionValueSerializer();
        $result = $serializer->serializeValues(['upload' => $file], true);

        self::assertSame([
            'uid' => 17,
            'identifier' => '/user_upload/cv.pdf',
            'name' => 'cv.pdf',
        ], $result['upload']);
    }

    #[Test]
    public function fileIsDroppedToNullWhenUploadsExcluded(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(17);
        $file->method('getIdentifier')->willReturn('/user_upload/cv.pdf');
        $file->method('getName')->willReturn('cv.pdf');

        $serializer = new SubmissionValueSerializer();
        $result = $serializer->serializeValues(['upload' => $file], false);

        self::assertNull($result['upload']);
    }
}
