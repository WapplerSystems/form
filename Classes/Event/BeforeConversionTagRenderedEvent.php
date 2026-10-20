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

namespace TYPO3\CMS\Form\Event;

use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Dispatched by ConversionTagResolver before a successful submission is
 * announced to the frontend — as data-form-conversion attribute on the
 * confirmation output, or as form_conversion query parameter on a redirect.
 *
 * $conversionTag starts as the form's renderingOptions.conversionTag (null if
 * the form has none). Listeners may replace it, e.g. to derive the tag from a
 * submitted value, or set it to null to suppress the announcement. The value a
 * listener sets is validated like the configured one; an invalid tag is
 * dropped, not rendered.
 */
final class BeforeConversionTagRenderedEvent
{
    public function __construct(
        public ?string $conversionTag,
        public readonly FormRuntime $formRuntime,
    ) {}
}
