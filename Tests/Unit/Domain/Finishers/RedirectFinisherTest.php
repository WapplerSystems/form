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

namespace TYPO3\CMS\Form\Tests\Unit\Domain\Finishers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\RedirectFinisher;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\ProcessableValueFormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\ConversionTagResolver;
use TYPO3\CMS\Form\Service\FormValueResolver;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RedirectFinisherTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @param string|int|null $pageUid
     */
    #[DataProvider('pageUidOptionForFinisherAcceptsVariousPageRepresentationsDataProvider')]
    #[Test]
    public function pageUidOptionForFinisherAcceptsVariousPageRepresentations($pageUid, int $expectedPage): void
    {
        $uriPrefix = 'https://site.test/?id=';
        $contentObjectRendererMock = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRendererMock->method('createUrl')->willReturnCallback(static function (array $conf) use ($uriPrefix): string {
            return $uriPrefix . $conf['parameter'];
        });

        $serverRequest = (new ServerRequest())->withAttribute('extbase', new ExtbaseRequestParameters());
        $serverRequest = $serverRequest->withAttribute('currentContentObject', $contentObjectRendererMock);
        $contentObjectRendererMock->setRequest($serverRequest);
        $request = new Request($serverRequest);

        $finisherContextMock = $this->createMock(FinisherContext::class);
        $finisherContextMock->method('getRequest')->willReturn($request);
        $finisherContextMock->expects($this->once())->method('cancel');

        $translationServiceMock = $this->createMock(TranslationService::class);
        $translationServiceMock->method('translateFinisherOption')->with(self::anything())->willReturnArgument(3);

        $redirectFinisherMock = $this->getAccessibleMock(RedirectFinisher::class, null, [], '', false);
        $redirectFinisherMock->_set('options', [
            'pageUid' => $pageUid,
        ]);
        $redirectFinisherMock->injectTranslationService($translationServiceMock);
        GeneralUtility::addInstance(ConversionTagResolver::class, new ConversionTagResolver(new NoopEventDispatcher()));
        try {
            $redirectFinisherMock->execute($finisherContextMock);
            self::fail('RedirectFinisher did not throw expected exception.');
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (PropagateResponseException $e) {
            $response = $e->getResponse();
            self::assertSame($uriPrefix . $expectedPage, $response->getHeader('Location')[0]);
        }
    }

    public static function pageUidOptionForFinisherAcceptsVariousPageRepresentationsDataProvider(): array
    {
        return [
            'null' => [
                null,
                1,
            ],
            'no page' => [
                '',
                1,
            ],
            'page as integer' => [
                3,
                3,
            ],
            'page as string' => [
                '3',
                3,
            ],
            'page with table prefix' => [
                'pages_3',
                3,
            ],
        ];
    }

    #[Test]
    public function redirectTargetsKeepTheSubmittedValue(): void
    {
        $contentObjectRendererStub = self::createStub(ContentObjectRenderer::class);
        $contentObjectRendererStub->method('createUrl')->willReturnCallback(static function (array $conf): string {
            return 'https://site.test/?id=' . $conf['parameter'] . $conf['additionalParams'] . '#' . $conf['section'];
        });

        $serverRequest = (new ServerRequest())
            ->withAttribute('extbase', new ExtbaseRequestParameters())
            ->withAttribute('currentContentObject', $contentObjectRendererStub);
        $contentObjectRendererStub->setRequest($serverRequest);

        $finisherContextMock = $this->createMock(FinisherContext::class);
        $finisherContextMock->method('getRequest')->willReturn(new Request($serverRequest));
        $finisherContextMock->method('getFormRuntime')->willReturn($this->buildFormRuntimeWithOptionableElement());
        $finisherContextMock->expects($this->once())->method('cancel');

        $translationServiceStub = self::createStub(TranslationService::class);
        $translationServiceStub->method('translateFinisherOption')->willReturnArgument(3);

        $redirectFinisherMock = $this->getAccessibleMock(RedirectFinisher::class, null, [], '', false);
        $redirectFinisherMock->_set('options', [
            'pageUid' => '{topic}',
            'additionalParameters' => 'department={department}',
            'fragment' => '{department}',
        ]);
        $redirectFinisherMock->injectTranslationService($translationServiceStub);
        $redirectFinisherMock->injectFormValueResolver(new FormValueResolver($translationServiceStub));
        GeneralUtility::addInstance(ConversionTagResolver::class, new ConversionTagResolver(new NoopEventDispatcher()));

        try {
            $redirectFinisherMock->execute($finisherContextMock);
            self::fail('RedirectFinisher did not throw expected exception.');
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (PropagateResponseException $e) {
            self::assertSame(
                'https://site.test/?id=3&department=sales#sales',
                $e->getResponse()->getHeader('Location')[0]
            );
        }
    }

    public static function conversionTagIsAppendedDataProvider(): array
    {
        return [
            'tag appended after existing parameters' => [
                ['additionalParameters' => 'department=sales'],
                'upgrade-anfrage',
                'https://site.test/?id=3&department=sales&form_conversion=upgrade-anfrage',
            ],
            'tag appended without other parameters' => [
                [],
                'lead:form_v2.1',
                'https://site.test/?id=3&&form_conversion=lead%3Aform_v2.1',
            ],
            'switched off per finisher' => [
                ['appendConversionTag' => false],
                'upgrade-anfrage',
                'https://site.test/?id=3&',
            ],
            'form without tag' => [
                [],
                null,
                'https://site.test/?id=3&',
            ],
            'invalid tag is dropped' => [
                [],
                'not valid',
                'https://site.test/?id=3&',
            ],
        ];
    }

    #[DataProvider('conversionTagIsAppendedDataProvider')]
    #[Test]
    public function conversionTagIsAppended(array $options, ?string $conversionTag, string $expectedLocation): void
    {
        $contentObjectRendererStub = self::createStub(ContentObjectRenderer::class);
        $contentObjectRendererStub->method('createUrl')->willReturnCallback(static function (array $conf): string {
            return 'https://site.test/?id=' . $conf['parameter'] . $conf['additionalParams'];
        });
        $serverRequest = (new ServerRequest())
            ->withAttribute('extbase', new ExtbaseRequestParameters())
            ->withAttribute('currentContentObject', $contentObjectRendererStub);

        $formDefinitionStub = self::createStub(FormDefinition::class);
        $formDefinitionStub->method('getRenderingOptions')->willReturn(
            $conversionTag === null ? [] : [ConversionTagResolver::RENDERING_OPTION => $conversionTag]
        );
        $formRuntimeStub = self::createStub(FormRuntime::class);
        $formRuntimeStub->method('getFormDefinition')->willReturn($formDefinitionStub);

        $finisherContextStub = self::createStub(FinisherContext::class);
        $finisherContextStub->method('getRequest')->willReturn(new Request($serverRequest));
        $finisherContextStub->method('getFormRuntime')->willReturn($formRuntimeStub);

        $translationServiceStub = self::createStub(TranslationService::class);
        $translationServiceStub->method('translateFinisherOption')->willReturnArgument(3);

        $redirectFinisherMock = $this->getAccessibleMock(RedirectFinisher::class, null, [], '', false);
        $redirectFinisherMock->_set('options', ['pageUid' => 3] + $options);
        $redirectFinisherMock->injectTranslationService($translationServiceStub);
        if (($options['appendConversionTag'] ?? true) !== false) {
            // Switched off, the finisher must not even resolve the tag.
            GeneralUtility::addInstance(ConversionTagResolver::class, new ConversionTagResolver(new NoopEventDispatcher()));
        }

        try {
            $redirectFinisherMock->execute($finisherContextStub);
            self::fail('RedirectFinisher did not throw expected exception.');
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (PropagateResponseException $e) {
            self::assertSame($expectedLocation, $e->getResponse()->getHeader('Location')[0]);
        }
    }

    private function buildFormRuntimeWithOptionableElement(): FormRuntime
    {
        // A page uid and a mail alias picked from a select, both of which lose
        // their meaning once the option label is substituted for them
        $submittedValues = ['topic' => '3', 'department' => 'sales'];
        $labels = ['topic' => 'Sales team', 'department' => 'Sales'];

        $elementStubs = [];
        foreach ($labels as $identifier => $label) {
            $elementStub = self::createStubForIntersectionOfInterfaces([
                FormElementInterface::class,
                ProcessableValueFormElementInterface::class,
            ]);
            $elementStub->method('processElementValue')->willReturn($label);
            $elementStubs[$identifier] = $elementStub;
        }

        $formDefinitionStub = self::createStub(FormDefinition::class);
        $formDefinitionStub->method('getElementByIdentifier')->willReturnCallback(
            static fn(string $identifier) => $elementStubs[$identifier] ?? null
        );

        $formRuntimeStub = self::createStub(FormRuntime::class);
        $formRuntimeStub->method('offsetExists')->willReturn(true);
        $formRuntimeStub->method('offsetGet')->willReturnCallback(
            static fn(string $identifier) => $submittedValues[$identifier] ?? null
        );
        $formRuntimeStub->method('getFormDefinition')->willReturn($formDefinitionStub);
        return $formRuntimeStub;
    }
}
