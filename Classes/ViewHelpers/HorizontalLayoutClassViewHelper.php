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
 * Mirrors the class-name-pattern convention already established by
 * GridColumnClassAutoConfigurationViewHelper (classPattern string with
 * {@placeholder} substitution instead of hard-coded return values), so the
 * actual CSS class vocabulary (Bootstrap by default) is fully overridable via
 * renderingOptions.layout.classPatterns.<part> without touching PHP - a site
 * package targeting a different CSS/grid framework only needs to override YAML.
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

    private const DEFAULT_SM = 3;
    private const DEFAULT_MD = 2;
    private const DEFAULT_GRID_SIZE = 12;

    /**
     * Fork-shipped Bootstrap defaults - fully overridable per form via
     * renderingOptions.layout.classPatterns.<part> (site package or hand-authored
     * form YAML), using the placeholders {@default}, {@sm}, {@md}, {@smInput},
     * {@mdInput} (see render()).
     */
    private const DEFAULT_CLASS_PATTERNS = [
        'container' => '{@default} row',
        'fieldset' => '{@default} row',
        'label' => 'col-sm-{@sm} col-md-{@md} col-form-label',
        'legend' => 'col-sm-{@sm} col-md-{@md} col-form-label pt-0',
        'column' => 'col-sm-{@smInput} col-md-{@mdInput}',
        'checkboxColumn' => 'col-sm-{@smInput} col-md-{@mdInput} offset-sm-{@sm} offset-md-{@md}',
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
        $pattern = $layout['classPatterns'][$part] ?? self::DEFAULT_CLASS_PATTERNS[$part] ?? '';
        if ($pattern === '') {
            return '';
        }

        $gridSize = (int)($layout['labelColumns']['gridSize'] ?? self::DEFAULT_GRID_SIZE);
        $sm = (int)($layout['labelColumns']['viewPorts']['sm']['numbersOfColumnsToUse'] ?? self::DEFAULT_SM);
        $md = (int)($layout['labelColumns']['viewPorts']['md']['numbersOfColumnsToUse'] ?? self::DEFAULT_MD);

        return trim(str_replace(
            ['{@default}', '{@sm}', '{@md}', '{@smInput}', '{@mdInput}'],
            [$default, (string)$sm, (string)$md, (string)($gridSize - $sm), (string)($gridSize - $md)],
            $pattern
        ));
    }
}
