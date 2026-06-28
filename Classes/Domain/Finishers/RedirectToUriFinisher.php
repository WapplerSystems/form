<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\Finishers;

use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

/**
 * Redirects to an arbitrary URI after the form has been submitted.
 * Complements core's `RedirectFinisher`, which only supports redirects
 * to TYPO3 pages via t3-page identifiers — this finisher supports
 * any URL string including external destinations.
 *
 * Options:
 *   uri        - destination URL (mandatory, may include placeholders like {fieldId})
 *   statusCode - HTTP status code, default 303
 *
 * Ported from wapplersystems/form_extended (Phase 3 of the migration).
 */
class RedirectToUriFinisher extends AbstractFinisher
{
    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'uri' => '',
        'statusCode' => 303,
    ];

    /**
     * @throws PropagateResponseException
     */
    protected function executeInternal(): void
    {
        $uri = (string)$this->parseOption('uri');
        $statusCode = (int)$this->parseOption('statusCode');

        if ($uri === '') {
            return;
        }

        // Cancel subsequent finishers — redirect terminates the request.
        $this->finisherContext->cancel();

        $uri = GeneralUtility::locationHeaderUrl($uri);
        $response = new RedirectResponse($uri, $statusCode);
        // @todo: Replace PropagateResponseException with returning a response once
        //        ContentObjectRenderer learns to handle finisher-produced responses
        //        outside of the Extbase bootstrap path.
        throw new PropagateResponseException($response, 1477070964);
    }
}
