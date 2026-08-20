<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Enum;

/**
 * Who produced a set of validation failures.
 *
 * Classification is per *session*, not per row: one automated run trips several
 * validators on the same submission, so counting rows says how noisy an attack
 * is while counting sessions says how many actors there were. On the site this
 * was built for, 16,096 rows came from 141 sessions — the row count alone would
 * have suggested a traffic problem rather than a dozen bots.
 *
 * Deliberately three buckets rather than a bot/human switch, because the
 * evidence does not support a binary answer:
 *
 *  - BOTS: the session tripped the honeypot or the entropy filter at least once.
 *    Both are conclusive. A human cannot fill a field positioned off-screen, and
 *    a human does not submit `vOYhcWlrcTafTMSelBkM` as a name.
 *  - SUSPECTED: the session only ever tripped the minimum-fill-time check. Mostly
 *    automated, but a real visitor on a short form genuinely can submit inside
 *    the window, so lumping these in with the bots would overstate what is known.
 *  - HUMANS: neither. This is the bucket worth reading — the usability signal that
 *    the attack volume otherwise buries.
 *
 * Two codes are deliberately NOT bot signals:
 *
 *  - The challenge failure (1755648001) fires for every client without a working
 *    JavaScript engine, which includes real visitors who disabled it. Treating it
 *    as automation would quietly reclassify people as bots.
 *  - Plain "field is empty" (1221560718) is the most human failure there is.
 */
enum ValidationAudience: string
{
    case ALL = 'all';
    case BOTS = 'bots';
    case SUSPECTED = 'suspected';
    case HUMANS = 'humans';

    /**
     * Error codes that identify automation beyond reasonable doubt.
     *
     * 1476396435 — honeypot: a CSS-hidden field with a per-session random name
     *              was filled in.
     * 1717686001 — EntropySpam: the free text is machine-generated.
     *
     * @return list<int>
     */
    public static function conclusiveBotCodes(): array
    {
        return [1476396435, 1717686001];
    }

    /**
     * Codes that suggest automation without settling it.
     *
     * 1755648002 — MinimumFillTime: submitted faster than the configured floor.
     *
     * @return list<int>
     */
    public static function suspectedBotCodes(): array
    {
        return [1755648002];
    }

    public function label(): string
    {
        return 'LLL:EXT:form/Resources/Private/Language/Database.xlf:validationStats.audience.' . $this->value;
    }
}
