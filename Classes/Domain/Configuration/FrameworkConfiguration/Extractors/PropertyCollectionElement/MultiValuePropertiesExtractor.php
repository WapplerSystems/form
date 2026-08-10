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

namespace TYPO3\CMS\Form\Domain\Configuration\FrameworkConfiguration\Extractors\PropertyCollectionElement;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Form\Domain\Configuration\FrameworkConfiguration\Extractors\AbstractExtractor;

/**
 * @internal
 */
class MultiValuePropertiesExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $formElementType, $propertyCollectionName, $propertyCollectionIndex, $propertyCollectionEditorIndex] = $matches;

        if (
            $value !== 'Inspector-PropertyGridEditor'
            && $value !== 'Inspector-MultiSelectEditor'
            && $value !== 'Inspector-CountrySelectEditor'
            && $value !== 'Inspector-ValidationErrorMessageEditor'
            // WapplerSystems fork: finisher variants editor — its propertyPath
            // ("options.variants") is a multi-value prefix so nested finisher
            // variant paths survive the editor save.
            && $value !== 'Inspector-VariantsEditor'
            // WapplerSystems fork (Feature 7): finisher translation editor — its
            // propertyPath ("options.translation.overrides") is a multi-value prefix so
            // per-language finisher option overrides survive the editor save.
            && $value !== 'Inspector-TranslationEditor'
        ) {
            return;
        }

        $propertyPath = implode(
            '.',
            [
                'formElementsDefinition',
                $formElementType,
                'formEditor',
                'propertyCollections',
                $propertyCollectionName,
                $propertyCollectionIndex,
                'editors',
                $propertyCollectionEditorIndex,
                'propertyPath',
            ]
        );
        $propertyValue = ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), $propertyPath, '.');

        $result = $this->extractorDto->getResult();

        if (
            $value === 'Inspector-PropertyGridEditor'
            || $value === 'Inspector-MultiSelectEditor'
            // WapplerSystems fork: register the finisher variants editor's
            // propertyPath under the collection element so finishers.<n>.options.variants.*
            // is allowed on save.
            || $value === 'Inspector-VariantsEditor'
            // WapplerSystems fork (Feature 7): same for the finisher translation editor,
            // so finishers.<n>.options.translation.overrides.* is allowed on save.
            || $value === 'Inspector-TranslationEditor'
        ) {
            $identifierPath = implode(
                '.',
                [
                    'formElementsDefinition',
                    $formElementType,
                    'formEditor',
                    'propertyCollections',
                    $propertyCollectionName,
                    $propertyCollectionIndex,
                    'identifier',
                ]
            );
            $identifier = ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), $identifierPath, '.');

            $result['formElements'][$formElementType]['collections'][$propertyCollectionName][$identifier]['multiValueProperties'][] = $propertyValue;
            if ($value === 'Inspector-PropertyGridEditor') {
                $result['formElements'][$formElementType]['collections'][$propertyCollectionName][$identifier]['multiValueProperties'][] = 'defaultValue';
            }
        } else {
            $result['formElements'][$formElementType]['multiValueProperties'][] = $propertyValue;
        }

        $this->extractorDto->setResult($result);
    }
}
