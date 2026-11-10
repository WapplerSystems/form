<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Form\Controller\JavaScriptLabelsController;

/**
 * release/v13 only: serves the label domains the v14 editor scripts import as
 * `~labels/<domain>`. 13.4 has no virtual label modules; the import map points
 * those specifiers at Resources/Public/JavaScript/compat/labels/, and those
 * shims fetch their labels from this route.
 */
return [
    'form_javascript_labels' => [
        'path' => '/form/javascript-labels',
        'target' => JavaScriptLabelsController::class . '::labelsAction',
    ],
];
