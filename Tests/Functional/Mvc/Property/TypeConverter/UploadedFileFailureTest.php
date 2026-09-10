<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Form\Tests\Functional\Mvc\Property\TypeConverter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter;
use TYPO3\CMS\Form\Tests\Functional\SetsUpAdminBackendUserTrait;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * An upload that fails deep inside FAL must come back as a property mapping
 * error, whatever the thrown exception carries as its code.
 *
 * `Throwable::getCode()` is an int only by convention: PDO reports an SQLSTATE
 * string, AWS-based drivers report their own error keys. Handing that straight
 * to `Error::__construct()`, which is typed `int`, replaced the handled upload
 * failure with a fatal TypeError - an error page instead of a field message,
 * and the original exception lost along with it.
 */
final class UploadedFileFailureTest extends FunctionalTestCase
{
    use SetsUpAdminBackendUserTrait;

    protected array $coreExtensionsToLoad = ['form'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminBackendUser();
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin/user_upload/');
    }

    protected function tearDown(): void
    {
        $uploadPath = $this->instancePath . '/fileadmin/user_upload/';
        if (is_dir($uploadPath)) {
            GeneralUtility::rmdir($uploadPath, true);
        }
        parent::tearDown();
    }

    public static function exceptionCodeDataProvider(): \Generator
    {
        yield 'int code is kept' => [1471715915, 1471715915];
        // SQLSTATE, the way PDOException reports it.
        yield 'numeric string code is kept' => ['23000', 23000];
        // The shapes that used to be fatal.
        yield 'non-numeric string code falls back' => ['AccessDenied', 1789034400];
        yield 'empty code falls back' => ['', 1789034400];
    }

    #[DataProvider('exceptionCodeDataProvider')]
    #[Test]
    public function convertFromReturnsErrorWhateverCodeTheExceptionCarries(
        string|int $thrownCode,
        int $expectedCode,
    ): void {
        $subject = $this->subjectFailingWith(self::failureWithCode($thrownCode));

        $result = $subject->convertFrom(
            [
                'error' => \UPLOAD_ERR_OK,
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/php-' . $expectedCode,
                'size' => 1024,
            ],
            FileReference::class,
            [],
            $this->uploadConfiguration(),
        );

        self::assertInstanceOf(Error::class, $result);
        self::assertSame($expectedCode, $result->getCode());
        self::assertSame('Storage rejected the file', $result->getMessage());
    }

    /**
     * `\Exception::__construct()` only accepts an int code, so a string code
     * can be produced the way the real subclasses do it: by assigning the
     * property directly.
     */
    private static function failureWithCode(string|int $code): \RuntimeException
    {
        $failure = new class ('Storage rejected the file') extends \RuntimeException {
            public function assignCode(string|int $code): void
            {
                $this->code = $code;
            }
        };
        $failure->assignCode($code);

        return $failure;
    }

    private function uploadConfiguration(): PropertyMappingConfiguration
    {
        $configuration = new PropertyMappingConfiguration();
        $configuration->setTypeConverterOption(
            UploadedFileReferenceConverter::class,
            UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER,
            '1:/user_upload/'
        );

        return $configuration;
    }

    /**
     * The converter takes its dependencies through inject methods, so a
     * subclass can be built by hand and wired from the container.
     */
    private function subjectFailingWith(\Throwable $failure): UploadedFileReferenceConverter
    {
        $subject = new class extends UploadedFileReferenceConverter {
            public \Throwable $failure;

            protected function importUploadedResource(
                array $uploadInfo,
                PropertyMappingConfigurationInterface $configuration
            ): PseudoFileReference {
                throw $this->failure;
            }
        };
        $subject->failure = $failure;
        $subject->injectResourceFactory($this->get(ResourceFactory::class));
        $subject->injectEventDispatcher($this->get(EventDispatcherInterface::class));
        $subject->injectHashService($this->get(HashService::class));
        $subject->injectPersistenceManager($this->get(PersistenceManagerInterface::class));
        $subject->injectStorageRepository($this->get(StorageRepository::class));

        return $subject;
    }
}
