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

namespace TYPO3\CMS\Form\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\ViewHelpers\RenderRenderableViewHelper;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

final class RenderFormValueViewHelperTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    protected array $coreExtensionsToLoad = ['form'];

    public static function renderDataProvider(): array
    {
        return [
            'render processed value' => [
                '<formvh:renderFormValue renderable="{element}" as="var">{var.processedValue}</formvh:renderFormValue>',
                'element value',
            ],
            'uses local variable scope' => [
                '<formvh:renderFormValue renderable="{element}" as="var"></formvh:renderFormValue>{var.processedValue}',
                '',
            ],
        ];
    }

    #[DataProvider('renderDataProvider')]
    #[Test]
    public function render(string $template, string $expected): void
    {
        // Init ConfigurationManagerInterface stateful singleton, usually done by extbase bootstrap
        $this->get(ExtbaseConfigurationManagerInterface::class)->setRequest(
            (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
        );
        $definition = $this->buildFormDefinition();
        $runtime = $definition->bind($this->buildExtbaseRequest());

        $element = $definition->getElementByIdentifier('text-1');

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getVariableProvider()->add('element', $element);
        $context->getViewHelperVariableContainer()
            ->add(RenderRenderableViewHelper::class, 'formRuntime', $runtime);
        $context->getTemplatePaths()->setTemplateSource($template);
        self::assertSame($expected, (new TemplateView($context))->render());
    }

    #[Test]
    public function renderResolvesSingleSelectOptionToItsLabel(): void
    {
        self::assertSame(
            'Mr.',
            $this->renderElement('select-1', '{var.processedValue}', 'mr')
        );
    }

    #[Test]
    public function renderResolvesMultiSelectOptionsToTheirLabels(): void
    {
        self::assertSame(
            'Mr.|Mrs.|',
            $this->renderElement('multi-1', '<f:for each="{var.processedValue}" as="label">{label}|</f:for>', ['mr', 'mrs'])
        );
    }

    #[Test]
    public function renderConvertsDateValueToString(): void
    {
        self::assertSame(
            '15.01.2025',
            $this->renderElement('date-1', '{var.processedValue}', new \DateTime('2025-01-15'))
        );
    }

    /**
     * A multiple upload arrives as an ObjectStorage, so the ViewHelper reports
     * the field as a multi-value one and a template iterates its processed
     * value. FileUpload used to collapse that storage into a single string,
     * which made the <f:for> below fail with "The argument \"each\" was
     * registered with type \"array\", but is of type \"string\"" - and with it
     * every submission of a form carrying a multiple upload.
     */
    #[Test]
    public function renderListsEveryFileOfAMultipleUpload(): void
    {
        $files = new ObjectStorage();
        $files->attach($this->mockFileReference('stand-vorne.jpg'));
        $files->attach($this->mockFileReference('stand-hinten.png'));

        self::assertSame(
            '1|stand-vorne.jpg|stand-hinten.png|',
            $this->renderElement(
                'upload-1',
                '{var.isMultiValue}|<f:for each="{var.processedValue}" as="fileName">{fileName}|</f:for>',
                $files
            )
        );
    }

    #[Test]
    public function renderReportsASingleUploadAsSingleValue(): void
    {
        self::assertSame(
            '|stand-vorne.jpg',
            $this->renderElement(
                'upload-1',
                '{var.isMultiValue}|{var.processedValue}',
                $this->mockFileReference('stand-vorne.jpg')
            )
        );
    }

    private function mockFileReference(string $fileName): FileReference
    {
        $originalResource = $this->createMock(CoreFileReference::class);
        $originalResource->method('getName')->willReturn($fileName);

        $fileReference = $this->createMock(FileReference::class);
        $fileReference->method('getOriginalResource')->willReturn($originalResource);

        return $fileReference;
    }

    private function renderElement(string $identifier, string $body, mixed $value): string
    {
        // Init ConfigurationManagerInterface stateful singleton, usually done by extbase bootstrap
        $this->get(ExtbaseConfigurationManagerInterface::class)->setRequest(
            (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
        );
        $definition = $this->buildFormDefinition();
        $runtime = $definition->bind($this->buildExtbaseRequest());
        $runtime->getFormState()->setFormValue($identifier, $value);

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getVariableProvider()->add('element', $definition->getElementByIdentifier($identifier));
        $context->getViewHelperVariableContainer()
            ->add(RenderRenderableViewHelper::class, 'formRuntime', $runtime);
        $context->getTemplatePaths()->setTemplateSource(
            '<formvh:renderFormValue renderable="{element}" as="var">' . $body . '</formvh:renderFormValue>'
        );
        return (new TemplateView($context))->render();
    }

    private function buildExtbaseRequest(): Request
    {
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->initializeUserSessionManager();
        $serverRequest = (new ServerRequest())
            ->withAttribute('extbase', new ExtbaseRequestParameters())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('language', new SiteLanguage(0, 'en_US.UTF-8', new Uri('/'), []));

        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        return (new Request($serverRequest))->withPluginName('Formframework');
    }

    private function buildFormDefinition(): FormDefinition
    {
        $formFactory = $this->get(ArrayFormFactory::class);
        return $formFactory->build([
            'type' => 'Form',
            'identifier' => 'test',
            'label' => 'test',
            'prototypeName' => 'standard',
            'renderables' => [
                [
                    'type' => 'Page',
                    'identifier' => 'page-1',
                    'label' => 'Page',
                    'renderables' => [
                        [
                            'type' => 'Text',
                            'identifier' => 'text-1',
                            'label' => 'Text',
                            'defaultValue' => 'element value',
                        ],
                        [
                            'type' => 'SingleSelect',
                            'identifier' => 'select-1',
                            'label' => 'Single select',
                            'properties' => [
                                'options' => ['mr' => 'Mr.', 'mrs' => 'Mrs.'],
                            ],
                        ],
                        [
                            'type' => 'MultiSelect',
                            'identifier' => 'multi-1',
                            'label' => 'Multi select',
                            'properties' => [
                                'options' => ['mr' => 'Mr.', 'mrs' => 'Mrs.'],
                            ],
                        ],
                        [
                            'type' => 'Date',
                            'identifier' => 'date-1',
                            'label' => 'Date',
                        ],
                        [
                            'type' => 'FileUpload',
                            'identifier' => 'upload-1',
                            'label' => 'Upload',
                            'properties' => [
                                'allowedMimeTypes' => ['image/jpeg', 'image/png'],
                            ],
                        ],
                    ],
                ],
            ],
        ], null, new ServerRequest());
    }
}
