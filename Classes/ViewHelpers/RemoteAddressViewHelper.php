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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the remote client IP address. Respects
 * $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] via
 * GeneralUtility::getIndpEnv('REMOTE_ADDR') — does NOT trust raw
 * $_SERVER['REMOTE_ADDR'] when a configured trusted proxy is in front.
 *
 * Typical use cases: append client IP to email submissions for audit /
 * abuse-reporting, log the IP via a finisher, render the IP in a
 * confirmation page.
 *
 * Fluid usage:
 *   {namespace formvh=TYPO3\CMS\Form\ViewHelpers}
 *   <formvh:remoteAddress />
 */
final class RemoteAddressViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        return (string)GeneralUtility::getIndpEnv('REMOTE_ADDR');
    }
}
