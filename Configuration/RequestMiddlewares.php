<?php

declare(strict_types=1);

/*
 * Middleware registration file for the WapplerSystems fork.
 * Keep this short — upstream EXT:form does not register middlewares,
 * so any entry here is fork-added. Document each entry inline.
 */

use TYPO3\CMS\Form\WapplerSystems\Middleware\PasswordPolicyEndpoint;

return [
    'frontend' => [
        // WapplerSystems fork: exposes the FE password policy as JSON
        // under /_form/password-policy/ so form-side JavaScript can
        // render a live policy-compliance indicator next to a password
        // field. The middleware runs after site resolution (so it has
        // access to the active site language for label translation)
        // but BEFORE the page resolver so the JSON URL never enters
        // page-not-found lookup.
        'wapplersystems/form/password-policy' => [
            'target' => PasswordPolicyEndpoint::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/page-resolver'],
        ],
    ],
];
