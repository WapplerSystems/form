<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 CMS Form (WapplerSystems fork)',
    'description' => 'WapplerSystems fork of typo3/cms-form: extended backend editor (variants/conditions UI), additional events, cross-field validators (e.g. entropy spam filter).',
    'category' => 'misc',
    'state' => 'stable',
    'author' => 'Sven Wappler, TYPO3 Core Team',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => 'WapplerSystems',
    'version' => '14.3.20',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.3.99',
            'frontend' => '14.3.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
