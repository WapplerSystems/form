<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Domain\Finishers;

use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

/**
 * Conditional EmailFinisher that fires only when a form field evaluates
 * to truthy. Typical use case: "Send me a copy" checkbox on a contact
 * form — when checked, this finisher sends a duplicate of the submission
 * email to the sender.
 *
 * The condition is read from `conditionFieldName` (the identifier of a
 * form element) and the finisher's `isEnabled()` short-circuits to
 * false when that field is empty/unchecked.
 *
 * For the actual mail composition, recipients, subject etc. — see
 * inherited EmailFinisher options. Typically configure the finisher so
 * its `recipients` resolves the sender's address via {emailFieldId}.
 *
 * Ported from wapplersystems/form_extended (Phase 3 of the migration).
 *
 * @see EmailFinisher
 */
class CopyToSenderEmailFinisher extends EmailFinisher
{
    public function isEnabled(): bool
    {
        // First respect the standard renderingOptions.enabled gate.
        if (isset($this->options['renderingOptions']['enabled'])
            && (bool)$this->parseOption('renderingOptions.enabled') !== true
        ) {
            return false;
        }

        $conditionFieldName = $this->parseOption('conditionFieldName');
        if ($conditionFieldName === null || $conditionFieldName === '') {
            throw new FinisherException(
                'The option "conditionFieldName" must be set for the CopyToSenderEmailFinisher.',
                1612660449,
            );
        }

        return (bool)$conditionFieldName;
    }
}
