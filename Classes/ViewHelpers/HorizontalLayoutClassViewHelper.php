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

use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Scope: frontend
 *
 * WapplerSystems fork: resolves the CSS class(es) for one "part" (container,
 * fieldset, label, legend, column, checkboxColumn) of a form field, depending
 * on the form-wide renderingOptions.layout setting (orientation + label/input
 * column split, editable per form via the "layoutOrientation"/"layoutLabelColumns"
 * Inspector editors on the Form root element, see Configuration/Form/Base/FormElements/Form.yaml).
 *
 * The set of breakpoints ("Stufen") is itself fully YAML-defined, exactly like
 * GridRow/GridColumn's own gridColumnClassAutoConfiguration: each viewport
 * entry under renderingOptions.layout.labelColumns.viewPorts carries its own
 * numbersOfColumnsToUse plus its own classPattern/offsetClassPattern strings
 * (with a {@numbersOfColumnsToUse} placeholder, same convention as
 * GridColumnClassAutoConfigurationViewHelper's classPattern). A site package
 * can add, remove or reorder breakpoints (e.g. add "lg"), or target a
 * different CSS/grid framework, purely via YAML - no PHP changes needed. Only
 * a fallback default (sm/md, Bootstrap "col-*"/"offset-*") ships here, used
 * when renderingOptions.layout.labelColumns.viewPorts is entirely unset.
 *
 * When the form is not in horizontal orientation, this ViewHelper is a no-op:
 * additive parts (container/fieldset) pass the given $default class through
 * unchanged, replacement parts (label/legend/column/checkboxColumn) return an
 * empty string - existing "f:if" wrap/replace logic in the consuming partials
 * then behaves exactly as before this feature existed.
 */
final class HorizontalLayoutClassViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    private const DEFAULT_GRID_SIZE = 12;

    /**
     * Fork-shipped Bootstrap defaults - fully overridable per form via
     * renderingOptions.layout.labelColumns.viewPorts (site package or
     * hand-authored form YAML). Only used as a fallback when that path is
     * entirely unset; once a form/site package defines its own viewPorts map,
     * that one is used as-is (no partial merge with these defaults).
     */
    private const DEFAULT_VIEWPORTS = [
        'sm' => [
            'numbersOfColumnsToUse' => 3,
            'classPattern' => 'col-sm-{@numbersOfColumnsToUse}',
            'offsetClassPattern' => 'offset-sm-{@numbersOfColumnsToUse}',
        ],
        'md' => [
            'numbersOfColumnsToUse' => 2,
            'classPattern' => 'col-md-{@numbersOfColumnsToUse}',
            'offsetClassPattern' => 'offset-md-{@numbersOfColumnsToUse}',
        ],
    ];

    public function initializeArguments(): void
    {
        $this->registerArgument('element', RootRenderableInterface::class, 'A RootRenderableInterface instance', true);
        $this->registerArgument('part', 'string', 'One of: container, fieldset, label, legend, column, checkboxColumn', true);
        $this->registerArgument('default', 'string', 'Base class to extend (only used for the additive "container"/"fieldset" parts)', false, '');
    }

    public function render(): string
    {
        /** @var \TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface $element */
        $element = $this->arguments['element'];
        $default = (string)$this->arguments['default'];
        $layout = $element->getRootForm()->getRenderingOptions()['layout'] ?? [];

        if (($layout['orientation'] ?? 'vertical') !== 'horizontal') {
            return $default;
        }

        $part = $this->arguments['part'];
        if ($part === 'container' || $part === 'fieldset') {
            return trim($default . ' row');
        }

        $gridSize = (int)($layout['labelColumns']['gridSize'] ?? self::DEFAULT_GRID_SIZE);
        $viewPorts = $layout['labelColumns']['viewPorts'] ?? null;
        if (!is_array($viewPorts) || $viewPorts === []) {
            $viewPorts = self::DEFAULT_VIEWPORTS;
        }

        $labelClasses = [];
        $columnClasses = [];
        $offsetClasses = [];
        foreach ($viewPorts as $viewPortConfig) {
            if (!is_array($viewPortConfig)) {
                continue;
            }
            $numbersOfColumnsToUse = (int)($viewPortConfig['numbersOfColumnsToUse'] ?? 0);
            $classPattern = (string)($viewPortConfig['classPattern'] ?? '');
            $offsetClassPattern = (string)($viewPortConfig['offsetClassPattern'] ?? '');

            if ($classPattern !== '') {
                $labelClasses[] = str_replace('{@numbersOfColumnsToUse}', (string)$numbersOfColumnsToUse, $classPattern);
                $columnClasses[] = str_replace('{@numbersOfColumnsToUse}', (string)max(0, $gridSize - $numbersOfColumnsToUse), $classPattern);
            }
            if ($offsetClassPattern !== '' && $numbersOfColumnsToUse > 0) {
                $offsetClasses[] = str_replace('{@numbersOfColumnsToUse}', (string)$numbersOfColumnsToUse, $offsetClassPattern);
            }
        }

        return match ($part) {
            'label' => trim(implode(' ', $labelClasses) . ' col-form-label'),
            'legend' => trim(implode(' ', $labelClasses) . ' col-form-label pt-0'),
            'column' => trim(implode(' ', $columnClasses)),
            'checkboxColumn' => trim(implode(' ', [...$columnClasses, ...$offsetClasses])),
            default => '',
        };
    }
}
