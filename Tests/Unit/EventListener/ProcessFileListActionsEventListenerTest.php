<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;
use TYPO3\CMS\Form\EventListener\ProcessFileListActionsEventListener;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The listener hides edit/view/replace/rename/download on `*.form.yaml`, because
 * a form definition must be changed through the form editor, not the file list.
 *
 * Covered here because the same listener already took the whole file module down
 * once: release/v14 removes the actions with
 * ProcessFileListActionsEvent::removeAction(), which 13.4's event does not have,
 * and the fatal Error surfaced as an exception page in place of File >
 * fileadmin > form_definitions. A unit test is the cheap guard - it fails at
 * once if the listener ever reaches for an API this branch's event lacks.
 */
final class ProcessFileListActionsEventListenerTest extends UnitTestCase
{
    /**
     * Every action the file list offers in 13.4, in its own order.
     *
     * @return array<string, string>
     */
    private function allActions(): array
    {
        $actions = [];
        foreach ([
            'edit', 'metadata', 'translations', 'view', 'replace', 'rename', 'download',
            'upload', 'info', 'delete', 'copy', 'cut', 'paste', 'updateOnlineMedia',
        ] as $name) {
            $actions[$name] = '<a data-action="' . $name . '"></a>';
        }
        return $actions;
    }

    /**
     * @param array<string, string> $actions
     */
    private function dispatch(object $resource, array $actions): ProcessFileListActionsEvent
    {
        $event = new ProcessFileListActionsEvent($resource, $actions);
        (new ProcessFileListActionsEventListener())->__invoke($event);
        return $event;
    }

    private function formDefinitionFile(): File
    {
        $file = $this->createMock(File::class);
        $file->method('getCombinedIdentifier')->willReturn('1:/form_definitions/contact.form.yaml');
        return $file;
    }

    #[Test]
    public function actionsThatWouldEditAFormDefinitionAreRemoved(): void
    {
        $event = $this->dispatch($this->formDefinitionFile(), $this->allActions());

        self::assertSame(
            ['metadata', 'translations', 'upload', 'info', 'delete', 'copy', 'cut', 'paste', 'updateOnlineMedia'],
            array_keys($event->getActionItems()),
            'Only edit, view, replace, rename and download may be removed - and the order of the rest must survive.',
        );
    }

    #[Test]
    public function actionsTheInstallationDoesNotOfferAreNotAProblem(): void
    {
        // A shorter action set must not produce a notice or an error: the
        // removal works by key, so an absent action is simply absent.
        $event = $this->dispatch($this->formDefinitionFile(), ['info' => '<a></a>', 'delete' => '<a></a>']);

        self::assertSame(['info', 'delete'], array_keys($event->getActionItems()));
    }

    #[Test]
    public function otherFilesKeepEveryAction(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getCombinedIdentifier')->willReturn('1:/user_upload/prospectus.pdf');

        $event = $this->dispatch($file, $this->allActions());

        self::assertSame(array_keys($this->allActions()), array_keys($event->getActionItems()));
    }

    #[Test]
    public function foldersKeepEveryAction(): void
    {
        // isFile() is false for a folder, so the listener returns before it ever
        // asks for a combined identifier.
        $event = $this->dispatch($this->createMock(Folder::class), $this->allActions());

        self::assertSame(array_keys($this->allActions()), array_keys($event->getActionItems()));
    }
}
