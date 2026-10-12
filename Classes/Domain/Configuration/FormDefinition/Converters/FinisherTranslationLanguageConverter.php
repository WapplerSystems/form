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

namespace TYPO3\CMS\Form\Domain\Configuration\FormDefinition\Converters;

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * @internal
 */
class FinisherTranslationLanguageConverter extends AbstractConverter
{
    /**
     * If "finishers.x.options.translation.language" is null then set the value to "" and remove
     * the hmac.
     *
     * WapplerSystems fork: upstream rewrites every empty value to "default". Here "" is a
     * selectable option of its own ("frontend language", see
     * InjectSiteLanguagesIntoEmailFinisherEditor) and "default" is no longer offered, so the
     * rewritten value failed validation on save ("No hmac found for property
     * options.translation.language") - and would have pinned the mail to the XLF source
     * language. "" therefore stays as it is; only null is normalised.
     *
     * @param mixed $value
     */
    public function __invoke(string $key, $value): void
    {
        if ($value !== null) {
            return;
        }

        $formDefinition = $this->converterDto->getFormDefinition();

        $formDefinition = ArrayUtility::setValueByPath($formDefinition, $key, '', '.');

        $hmacPropertyPathParts = explode('.', $key);
        $lastKeySegment = array_pop($hmacPropertyPathParts);
        $hmacPropertyPathParts[] = '_orig_' . $lastKeySegment;
        $hmacValuePath = implode('.', $hmacPropertyPathParts);

        if (ArrayUtility::isValidPath($formDefinition, $hmacValuePath, '.')) {
            $formDefinition = ArrayUtility::removeByPath($formDefinition, $hmacValuePath, '.');
        }

        $this->converterDto->setFormDefinition($formDefinition);
    }
}
