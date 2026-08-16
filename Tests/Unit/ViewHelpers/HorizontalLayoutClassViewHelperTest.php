<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\ViewHelpers\HorizontalLayoutClassViewHelper;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class HorizontalLayoutClassViewHelperTest extends UnitTestCase
{
    private function createElement(array $layout = []): GenericFormElement
    {
        $form = new FormDefinition('test-form');
        if ($layout !== []) {
            $form->setRenderingOption('layout', $layout);
        }
        $element = new GenericFormElement('test-element', 'Text');
        $element->setParentRenderable($form);
        return $element;
    }

    private function render(GenericFormElement $element, string $part, string $default = ''): string
    {
        $viewHelper = new HorizontalLayoutClassViewHelper();
        $viewHelper->setArguments(['element' => $element, 'part' => $part, 'default' => $default]);
        return $viewHelper->render();
    }

    #[Test]
    public function verticalOrientationPassesThroughDefaultForAdditiveParts(): void
    {
        $element = $this->createElement(['orientation' => 'vertical']);
        self::assertSame('form-element form-element-text mb-3', $this->render($element, 'container', 'form-element form-element-text mb-3'));
        self::assertSame('form-element form-element-radio mb-3', $this->render($element, 'fieldset', 'form-element form-element-radio mb-3'));
    }

    #[Test]
    public function verticalOrientationReturnsEmptyStringForReplacementParts(): void
    {
        $element = $this->createElement(['orientation' => 'vertical']);
        self::assertSame('', $this->render($element, 'label'));
        self::assertSame('', $this->render($element, 'legend'));
        self::assertSame('', $this->render($element, 'column'));
        self::assertSame('', $this->render($element, 'checkboxColumn'));
    }

    #[Test]
    public function missingLayoutOptionBehavesLikeVertical(): void
    {
        // renderingOptions.layout entirely unset (e.g. an old form definition
        // saved before this feature existed) must not error and must behave
        // exactly like an explicit "vertical" orientation.
        $element = $this->createElement([]);
        self::assertSame('unchanged', $this->render($element, 'container', 'unchanged'));
        self::assertSame('', $this->render($element, 'column'));
    }

    #[Test]
    public function horizontalOrientationWithoutLabelColumnsFallsBackToDefaultViewports(): void
    {
        // renderingOptions.layout.labelColumns entirely unset -> the PHP-side
        // default viewports (sm=3, md=2, Bootstrap col-*/offset-* classes) apply.
        $element = $this->createElement(['orientation' => 'horizontal']);
        self::assertSame('form-element form-element-text mb-3 row', $this->render($element, 'container', 'form-element form-element-text mb-3'));
        self::assertSame('col-sm-3 col-md-2 col-form-label', $this->render($element, 'label'));
        self::assertSame('col-sm-3 col-md-2 col-form-label pt-0', $this->render($element, 'legend'));
        self::assertSame('col-sm-9 col-md-10', $this->render($element, 'column'));
        self::assertSame('col-sm-9 col-md-10 offset-sm-3 offset-md-2', $this->render($element, 'checkboxColumn'));
    }

    #[Test]
    public function customViewportNumbersDeriveComplementCorrectly(): void
    {
        $element = $this->createElement([
            'orientation' => 'horizontal',
            'labelColumns' => [
                'viewPorts' => [
                    'sm' => ['numbersOfColumnsToUse' => 4, 'classPattern' => 'col-sm-{@numbersOfColumnsToUse}', 'offsetClassPattern' => 'offset-sm-{@numbersOfColumnsToUse}'],
                    'md' => ['numbersOfColumnsToUse' => 3, 'classPattern' => 'col-md-{@numbersOfColumnsToUse}', 'offsetClassPattern' => 'offset-md-{@numbersOfColumnsToUse}'],
                ],
            ],
        ]);
        self::assertSame('col-sm-4 col-md-3 col-form-label', $this->render($element, 'label'));
        self::assertSame('col-sm-8 col-md-9', $this->render($element, 'column'));
        self::assertSame('col-sm-8 col-md-9 offset-sm-4 offset-md-3', $this->render($element, 'checkboxColumn'));
    }

    #[Test]
    public function customGridSizeIsHonored(): void
    {
        $element = $this->createElement([
            'orientation' => 'horizontal',
            'labelColumns' => [
                'gridSize' => 24,
                'viewPorts' => [
                    'sm' => ['numbersOfColumnsToUse' => 6, 'classPattern' => 'col-sm-{@numbersOfColumnsToUse}'],
                ],
            ],
        ]);
        self::assertSame('col-sm-18', $this->render($element, 'column'));
    }

    #[Test]
    public function arbitraryNumberOfViewportsIsSupported(): void
    {
        // The set of breakpoints ("Stufen") is fully YAML-defined, exactly like
        // GridRow/GridColumn's own gridColumnClassAutoConfiguration - a site
        // package can add, remove or reorder viewports, e.g. add "lg", purely
        // via YAML, with no PHP changes.
        $element = $this->createElement([
            'orientation' => 'horizontal',
            'labelColumns' => [
                'viewPorts' => [
                    'xs' => ['numbersOfColumnsToUse' => 12, 'classPattern' => 'col-{@numbersOfColumnsToUse}'],
                    'sm' => ['numbersOfColumnsToUse' => 4, 'classPattern' => 'col-sm-{@numbersOfColumnsToUse}'],
                    'lg' => ['numbersOfColumnsToUse' => 3, 'classPattern' => 'col-lg-{@numbersOfColumnsToUse}'],
                ],
            ],
        ]);
        self::assertSame('col-12 col-sm-4 col-lg-3 col-form-label', $this->render($element, 'label'));
        self::assertSame('col-0 col-sm-8 col-lg-9', $this->render($element, 'column'));
    }

    #[Test]
    public function customClassPatternsAreFullyConfigurable(): void
    {
        // A site package targeting a different CSS/grid framework only needs
        // to override the classPattern/offsetClassPattern strings - no PHP.
        $element = $this->createElement([
            'orientation' => 'horizontal',
            'labelColumns' => [
                'viewPorts' => [
                    'sm' => [
                        'numbersOfColumnsToUse' => 5,
                        'classPattern' => 'my-grid-col-sm-{@numbersOfColumnsToUse}',
                        'offsetClassPattern' => 'my-grid-offset-sm-{@numbersOfColumnsToUse}',
                    ],
                ],
            ],
        ]);
        self::assertSame('my-grid-col-sm-5 col-form-label', $this->render($element, 'label'));
        self::assertSame('my-grid-col-sm-7', $this->render($element, 'column'));
        self::assertSame('my-grid-col-sm-7 my-grid-offset-sm-5', $this->render($element, 'checkboxColumn'));
    }
}
