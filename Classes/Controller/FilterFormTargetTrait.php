<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Controller;

/**
 * Builds the action target for a backend module's GET filter form.
 *
 * A GET submission replaces the whole query string of the form's action with
 * the form fields, so the request token the URI builder put there is gone.
 * RouteDispatcher::assertRequestToken() then throws
 * MissingRequestTokenException, RequestHandler redirects to the login route,
 * and for a user who is still logged in that route answers with the backend
 * shell - so the module frame renders the entire backend a second time instead
 * of the filtered list. It looks like the module broke, and the filter did in
 * fact not apply.
 *
 * The fix is to submit to the bare path and re-emit every query parameter as a
 * hidden field, where the browser leaves it alone. Splitting the URI rather
 * than naming the token explicitly keeps whatever else the URI builder decided
 * to put in the query (`id`, and anything a future TYPO3 version adds) intact.
 *
 * Shared by every module in this extension that filters through a GET form -
 * the form log views and the submission list. It lives in a trait rather than
 * in a common base class because FormSubmissionController is a plain
 * ActionController and has no reason to inherit the log controllers' doc-header
 * view switch.
 */
trait FilterFormTargetTrait
{
    /**
     * @return array{uri: string, hiddenFields: array<string, string>}
     */
    protected function buildFilterFormTarget(string $action = 'index'): array
    {
        $uri = (string)$this->uriBuilder->reset()->uriFor($action);

        $hiddenFields = [];
        foreach (explode('&', (string)parse_url($uri, PHP_URL_QUERY)) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $hiddenFields[urldecode($name)] = urldecode($value);
        }

        return [
            'uri' => (string)parse_url($uri, PHP_URL_PATH),
            'hiddenFields' => $hiddenFields,
        ];
    }
}
