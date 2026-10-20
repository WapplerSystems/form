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

namespace TYPO3\CMS\Form\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\BeforeConversionTagRenderedEvent;

/**
 * Resolves the conversion tag of a submitted form.
 *
 * A form sets renderingOptions.conversionTag to announce a successful
 * submission to the frontend under a stable name, independent of the runtime
 * identifier (which carries the content element uid). The confirmation
 * finisher renders it as data-form-conversion, the redirect finisher appends
 * it as form_conversion query parameter, and
 * Resources/Public/JavaScript/frontend/conversion.js turns both into a
 * "form:conversion" DOM event that analytics code can listen to.
 *
 * The tag ends up in markup, in a URL and in third-party analytics, so only a
 * conservative character set is accepted.
 */
final readonly class ConversionTagResolver
{
    public const RENDERING_OPTION = 'conversionTag';
    public const QUERY_PARAMETER = 'form_conversion';

    /**
     * Keep in sync with the pattern in conversion.ts.
     */
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/';

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function resolve(FormRuntime $formRuntime): ?string
    {
        $configured = $formRuntime->getFormDefinition()->getRenderingOptions()[self::RENDERING_OPTION] ?? null;
        $tag = is_string($configured) && $configured !== '' ? $configured : null;

        $event = $this->eventDispatcher->dispatch(new BeforeConversionTagRenderedEvent($tag, $formRuntime));

        return self::isValid($event->conversionTag) ? $event->conversionTag : null;
    }

    public static function isValid(?string $tag): bool
    {
        return $tag !== null && preg_match(self::PATTERN, $tag) === 1;
    }
}
