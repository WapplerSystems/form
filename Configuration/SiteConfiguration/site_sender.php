<?php

declare(strict_types=1);

/*
 * Sub-site-entity TCA for the WapplerSystems fork's site-sender feature.
 * Activated only when the `featureSiteEmail` extension flag is set;
 * editable in the BE Site Configuration module under the parent site.
 */

return [
    'ctrl' => [
        'label' => 'email',
        'title' => 'Email sender',
        'typeicon_classes' => [
            'default' => 'mimetypes-x-email',
        ],
    ],
    'columns' => [
        'email' => [
            'label' => 'Email address',
            'description' => 'The "From:" address used when this sender is selected on a form plugin.',
            'config' => [
                'type' => 'email',
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'name' => [
            'label' => 'Display name',
            'description' => 'Human-readable "From:" name (e.g. "Acme Sales").',
            'config' => [
                'type' => 'input',
                'required' => true,
                'eval' => 'trim',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'email,name',
        ],
    ],
];
