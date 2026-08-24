<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Decides which elements of a form are consent checkboxes.
 *
 * Shared by the e-mail templates (which fold consents into one summary row) and
 * by the consent log (which records them). Two answers to "is this a consent
 * field?" that could drift apart would be worse than either: the mail would
 * claim a consent the log never recorded, or the other way round.
 *
 * Stateless on purpose - EmailFinisher reaches for it through
 * GeneralUtility::makeInstance() rather than the constructor, because three
 * extensions in the wild subclass EmailFinisher and call parent::__construct()
 * with its current three arguments.
 *
 * @internal not part of public TYPO3 Core API
 */
class ConsentElementResolver
{
    /**
     * Consent kind of a field marked without naming one. Has no XLF caption on
     * purpose, so the summary falls back to the element's own label.
     */
    public const CUSTOM_CONSENT_KEY = 'custom';

    /**
     * The consent "kind" of an element, or null if it is not a consent field.
     *
     * One rule: `properties.isConsentField`, set on the element in the form
     * editor. No name matching, no type sniffing.
     *
     * An earlier draft guessed from the identifier, on the theory that the
     * upgrade wizards had left recognisable prefixes behind. Measured against
     * the thirteen consent checkboxes of the site this was written for, that
     * guess missed three - including a transfer-to-third-parties consent named
     * plainly `checkbox-1`, which is both the one no prefix can ever reach and
     * the one whose Art. 7(1) evidence matters most. A rule that silently
     * covers most cases is the worst kind for a legal record: nothing tells you
     * about the ones it dropped. Existing forms are stamped by a migration
     * wizard instead, which is auditable and runs once.
     *
     * `properties.consentKind` picks the caption when a field should read as
     * one of the known kinds ("finisher.email.consent.<kind>" in locallang.xlf)
     * rather than by its own label.
     */
    public function resolveConsentKey(RenderableInterface $element): ?string
    {
        if (!$element instanceof FormElementInterface) {
            return null;
        }

        $properties = $element->getProperties();
        if (!$this->isTrue($properties['isConsentField'] ?? null)) {
            return null;
        }

        $kind = $properties['consentKind'] ?? null;

        return is_string($kind) && $kind !== '' ? strtolower($kind) : self::CUSTOM_CONSENT_KEY;
    }

    /**
     * The form editor writes booleans, FlexForm and TypoScript overrides write
     * strings - "0" has to read as false the same way it does for finisher
     * options.
     */
    private function isTrue(mixed $value): bool
    {
        return !($value === false || $value === '0' || $value === '' || $value === 0 || $value === null);
    }

    /**
     * Every consent element of a form, in document order.
     *
     * @return array<int, array{element: RenderableInterface, key: string}>
     */
    public function findAll(FormRuntime $formRuntime): array
    {
        $found = [];
        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
            $key = $this->resolveConsentKey($element);
            if ($key !== null) {
                $found[] = ['element' => $element, 'key' => $key];
            }
        }

        return $found;
    }
}
