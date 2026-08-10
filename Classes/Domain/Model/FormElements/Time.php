<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\Model\FormElements;

use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\StringableFormElementInterface;

/**
 * A time form element backed by a DateTimeImmutable with the H:i format.
 * Renders as <input type="time">. The date portion of the resulting
 * DateTimeImmutable is the runtime "today" — only the time portion is
 * meaningful.
 *
 * Replaces the broken Time element from wapplersystems/form_extended,
 * which used a lossy "hours * 100 + minutes" integer encoding. This
 * implementation uses the standard Extbase DateTimeConverter, no custom
 * type-converter or data-type wrapper required.
 */
class Time extends AbstractFormElement implements StringableFormElementInterface
{
    public function initializeFormElement()
    {
        $this->setDataType(\DateTimeImmutable::class);
        $propertyMappingConfiguration = $this->getRootForm()
            ->getProcessingRule($this->getIdentifier())
            ->getPropertyMappingConfiguration();
        $propertyMappingConfiguration->setTypeConverterOption(
            DateTimeConverter::class,
            DateTimeConverter::CONFIGURATION_DATE_FORMAT,
            'H:i',
        );
    }

    /**
     * @param \DateTimeInterface|mixed $value
     */
    public function valueToString($value): string
    {
        if (!$value instanceof \DateTimeInterface) {
            return '';
        }
        $format = $this->properties['displayFormat'] ?? 'H:i';
        return $value->format($format);
    }
}
