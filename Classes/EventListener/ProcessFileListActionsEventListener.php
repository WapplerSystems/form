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

namespace TYPO3\CMS\Form\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;

/**
 * Event listener to disable certain actions when checking for form.yaml files.
 *
 * Deviates from release/v14: that branch calls
 * ProcessFileListActionsEvent::removeAction(), which 13.4's event does not have
 * - it exposes only getActionItems()/setActionItems(). Calling it threw a fatal
 * Error, and because the listener runs from FileList::render() the whole file
 * module went down with it: opening File > fileadmin > form_definitions
 * rendered an exception page instead of the folder. So the actions are removed
 * by rewriting the array here.
 *
 * @internal
 */
class ProcessFileListActionsEventListener
{
    protected const DISABLED_ACTIONS = ['edit', 'view', 'replace', 'rename', 'download'];

    #[AsEventListener('form-framework/form-definition-files')]
    public function __invoke(ProcessFileListActionsEvent $event): void
    {
        if (!$event->isFile() || !$event->getResource() instanceof AbstractFile) {
            return;
        }
        $fullIdentifier = $event->getResource()->getCombinedIdentifier();
        if (!str_ends_with($fullIdentifier, FormPersistenceManagerInterface::FORM_DEFINITION_FILE_EXTENSION)) {
            return;
        }

        // Keys are the action names; anything not in DISABLED_ACTIONS stays,
        // and an action the current TYPO3 version does not offer is simply
        // absent rather than an error.
        $event->setActionItems(
            array_diff_key($event->getActionItems(), array_flip(self::DISABLED_ACTIONS)),
        );
    }
}
