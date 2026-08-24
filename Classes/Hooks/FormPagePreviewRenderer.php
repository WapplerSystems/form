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

namespace TYPO3\CMS\Form\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Error\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Form\Mvc\Configuration\Exception\NoSuchFileException;
use TYPO3\CMS\Form\Mvc\Configuration\Exception\ParseErrorException;
use TYPO3\CMS\Form\Mvc\Persistence\Exception\PersistenceManagerException;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;

/**
 * Contains a preview rendering for the page module of CType="form_formframework"
 * @internal
 */
#[Autoconfigure(public: true)]
class FormPagePreviewRenderer extends StandardContentPreviewRenderer implements PreviewRendererInterface
{
    private const L10N_PREFIX = 'LLL:EXT:form/Resources/Private/Language/Database.xlf:';

    public function __construct(
        protected readonly FormPersistenceManagerInterface $formPersistenceManager,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly FlexFormService $flexFormService,
    ) {}

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        // TYPO3 v13 hands the raw record array here (v14's Record object with
        // has()/get() and FlexFormFieldValues does not exist yet), so pi_flexform
        // arrives as the serialized flexform string and has to be converted.
        $row = $item->getRecord();
        $flexFormData = $row['pi_flexform'] ?? [];
        if (is_string($flexFormData)) {
            $flexFormData = $this->flexFormService->convertFlexFormContentToArray($flexFormData);
        }
        if (!is_array($flexFormData)) {
            $this->logger?->warning(
                'Type "{type}" of field "pi_flexform" for record-uid "{uid}" is not valid. Must be either empty or set to one of: "string", "array".',
                ['type' => get_debug_type($flexFormData), 'uid' => $row['uid'] ?? 'UNKNOWN']
            );
            $flexFormData = [];
        }
        $persistenceIdentifier = $flexFormData['settings']['persistenceIdentifier'] ?? '';
        $languageService = $this->getLanguageService();
        if (!empty($persistenceIdentifier)) {
            try {
                try {
                    $formDefinition = $this->formPersistenceManager->load($persistenceIdentifier);
                    $formLabel = $formDefinition['label'];
                } catch (ParseErrorException $e) {
                    $formLabel = sprintf(
                        $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.invalidPersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                } catch (PersistenceManagerException $e) {
                    $formLabel = sprintf(
                        $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.inaccessiblePersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                } catch (Exception $e) {
                    $formLabel = sprintf(
                        $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.notExistingdPersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                }
            } catch (NoSuchFileException $e) {
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $formLabel = sprintf(
                    $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.notExistingdPersistenceIdentifier'),
                    $persistenceIdentifier
                );
            } catch (ParseErrorException $e) {
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $formLabel = sprintf(
                    $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration'),
                    $persistenceIdentifier
                );
            } catch (\Exception $e) {
                // Top level catch - FAL throws top level exceptions on missing files, eg. in getFileInfoByIdentifier() of LocalDriver
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $formLabel = sprintf(
                    $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.text'),
                    $persistenceIdentifier,
                    $e->getMessage()
                );
            }
        } else {
            $formLabel = $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.noPersistenceIdentifier');
        }
        // v13 has no RecordFieldPreviewProcessor; StandardContentPreviewRenderer
        // still provides linkEditContent() for the same purpose.
        $itemContent = $this->linkEditContent('<strong>' . htmlspecialchars($item->getContext()->getContentTypeLabels()['form_formframework']) . '</strong>', $row) . '<br />';
        return $itemContent . $this->linkEditContent(htmlspecialchars($formLabel), $row) . '<br />';
    }

    protected function addInvalidFrameworkConfigurationFlashMessage(string $persistenceIdentifier, \Exception $e): void
    {
        $languageService = $this->getLanguageService();
        $this->flashMessageService
            ->getMessageQueueByIdentifier('core.template.flashMessages')
            ->enqueue(
                new FlashMessage(
                    sprintf(
                        $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.text'),
                        $persistenceIdentifier,
                        $e->getMessage()
                    ),
                    $languageService->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.title'),
                    ContextualFeedbackSeverity::ERROR,
                    true
                )
            );
    }
}
