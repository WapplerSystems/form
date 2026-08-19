<?php

declare(strict_types=1);

/*
 * Middleware registration file for the WapplerSystems fork.
 * Keep this short — upstream EXT:form does not register middlewares,
 * so any entry here is fork-added. Document each entry inline.
 */

use TYPO3\CMS\Form\Middleware\PasswordPolicyEndpoint;

return [
    'frontend' => [
        // WapplerSystems fork: exposes the FE password policy as JSON
        // under /_form/password-policy/ so form-side JavaScript can
        // render a live policy-compliance indicator next to a password
        // field.
        //
        // Ordering: after site resolution, so the site (and therefore its
        // configured languages) is available for label translation, but
        // before base-redirect-resolver. The endpoint URL deliberately
        // carries no language prefix - it is fetched as an absolute path
        // from any page - and base-redirect-resolver 404s any path that
        // does not sit under a configured language base. Running before it
        // is what makes the unprefixed URL work at all; being before
        // page-resolver additionally keeps the JSON URL out of the
        // page-not-found lookup.
        'wapplersystems/form/password-policy' => [
            'target' => PasswordPolicyEndpoint::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
