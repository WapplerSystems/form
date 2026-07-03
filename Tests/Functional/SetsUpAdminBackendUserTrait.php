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

namespace TYPO3\CMS\Form\Tests\Functional;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Registers a minimal admin backend user in $GLOBALS['BE_USER'].
 *
 * In a standalone functional run of this package, a backend-typed request is
 * left in $GLOBALS['TYPO3_REQUEST']. As soon as a FAL storage is initialized,
 * core's StoragePermissionsAspect (listener on
 * AfterResourceStorageInitializationEvent) dereferences $GLOBALS['BE_USER'],
 * whose getBackendUser() return type is non-nullable BackendUserAuthentication.
 * Without a backend user this fatals with a TypeError.
 *
 * An admin user makes the aspect short-circuit (`!isAdmin()` is false) before it
 * touches file permissions or mounts, matching the real backend/form-editor
 * context in which these code paths run. This avoids depending on a be_users
 * fixture just to satisfy the aspect.
 */
trait SetsUpAdminBackendUserTrait
{
    protected function setUpAdminBackendUser(): void
    {
        $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $backendUser->user = ['uid' => 1, 'admin' => 1];
        $GLOBALS['BE_USER'] = $backendUser;
    }
}
