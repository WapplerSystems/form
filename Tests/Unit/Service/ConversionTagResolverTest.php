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

namespace TYPO3\CMS\Form\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\BeforeConversionTagRenderedEvent;
use TYPO3\CMS\Form\Service\ConversionTagResolver;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ConversionTagResolverTest extends UnitTestCase
{
    public static function configuredTagDataProvider(): array
    {
        return [
            'plain' => ['upgrade-anfrage', 'upgrade-anfrage'],
            'with dot, colon and underscore' => ['lead:form_v2.1', 'lead:form_v2.1'],
            'longest allowed' => [str_repeat('a', 64), str_repeat('a', 64)],
            'too long' => [str_repeat('a', 65), null],
            'leading dash' => ['-upgrade', null],
            'space' => ['upgrade anfrage', null],
            'markup' => ['"><script>', null],
            'umlaut' => ['anfrage-ü', null],
            'empty' => ['', null],
            'not a string' => [42, null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('configuredTagDataProvider')]
    #[Test]
    public function resolveAcceptsOnlyTheConservativeCharacterSet(mixed $configured, ?string $expected): void
    {
        $resolver = new ConversionTagResolver(new NoopEventDispatcher());

        self::assertSame($expected, $resolver->resolve($this->formRuntimeWith($configured)));
    }

    #[Test]
    public function listenerCanReplaceTheTag(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeConversionTagRenderedEvent) {
                    $event->conversionTag = $event->conversionTag . '-b2b';
                }
                return $event;
            }
        };

        $resolver = new ConversionTagResolver($dispatcher);

        self::assertSame('lead-b2b', $resolver->resolve($this->formRuntimeWith('lead')));
    }

    #[Test]
    public function tagSetByListenerIsValidatedToo(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeConversionTagRenderedEvent) {
                    $event->conversionTag = 'not valid';
                }
                return $event;
            }
        };

        $resolver = new ConversionTagResolver($dispatcher);

        self::assertNull($resolver->resolve($this->formRuntimeWith('lead')));
    }

    #[Test]
    public function listenerCanSuppressTheTag(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeConversionTagRenderedEvent) {
                    $event->conversionTag = null;
                }
                return $event;
            }
        };

        $resolver = new ConversionTagResolver($dispatcher);

        self::assertNull($resolver->resolve($this->formRuntimeWith('lead')));
    }

    private function formRuntimeWith(mixed $conversionTag): FormRuntime
    {
        $renderingOptions = $conversionTag === null ? [] : [ConversionTagResolver::RENDERING_OPTION => $conversionTag];
        $formDefinitionStub = self::createStub(FormDefinition::class);
        $formDefinitionStub->method('getRenderingOptions')->willReturn($renderingOptions);
        $formRuntimeStub = self::createStub(FormRuntime::class);
        $formRuntimeStub->method('getFormDefinition')->willReturn($formDefinitionStub);
        return $formRuntimeStub;
    }
}
