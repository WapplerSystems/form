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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\QueryBuilderPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Form\Domain\DTO\MailLogDemand;
use TYPO3\CMS\Form\Domain\Repository\MailLogRepository;
use TYPO3\CMS\Form\Enum\MailLogStatus;

/**
 * Backend module listing the outgoing-mail log.
 *
 * Exists because the log itself is only half an answer: a table nobody can read
 * does not tell anyone that a form's notification mail has been failing on every
 * submission. Which is what happened — for over a week, on a form whose whole
 * job was to notice exactly that.
 *
 * Structured for a second view. The doc-header carries a view switch and the
 * filter/pagination partials take the filter as one opaque array, so the planned
 * validation-statistics view is a new action plus one menu entry rather than a
 * rebuild.
 *
 * @internal not part of public TYPO3 Core API
 */
class MailLogController extends ActionController
{
    protected const PAGINATION_MAX = 20;

    /**
     * Window the form-identifier dropdown is built from. Longer than the default
     * listing range so a form that stopped sending recently is still selectable.
     */
    protected const FILTER_OPTIONS_DAYS = 180;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly ComponentFactory $componentFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly MailLogRepository $mailLogRepository,
    ) {}

    /**
     * @param array<string, mixed> $filter
     */
    protected function indexAction(int $page = 1, array $filter = []): ResponseInterface
    {
        $demand = MailLogDemand::fromArray($filter);

        $paginator = new QueryBuilderPaginator(
            $this->mailLogRepository->createDemandQueryBuilder($demand),
            max(1, $page),
            self::PAGINATION_MAX,
        );

        $moduleTemplate = $this->initializeModuleTemplate($this->request, $page, $demand);
        $moduleTemplate->assignMultiple([
            'paginator' => $paginator,
            'pagination' => new SimplePagination($paginator),
            'filter' => $demand->toArray(),
            'demand' => $demand,
            'counts' => $this->counts($demand),
            'statuses' => $this->statusOptions(),
            'formOptions' => $this->formOptions(),
            'stuckGraceMinutes' => (int)(MailLogRepository::STUCK_GRACE_SECONDS / 60),
        ]);

        return $moduleTemplate->renderResponse('Backend/MailLog/Index');
    }

    protected function showAction(int $uid): ResponseInterface
    {
        $row = $this->mailLogRepository->findByUid($uid);
        if ($row === null) {
            return $this->redirect('index');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->addButtonToButtonBar(
            $this->componentFactory->createLinkButton()
                ->setHref($this->uriBuilder->uriFor('index'))
                ->setTitle($this->translate('mailLog.back'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL))
        );
        $moduleTemplate->setTitle($this->translate('mailLog.headline'));
        $moduleTemplate->assignMultiple([
            'entry' => $row,
            'status' => MailLogStatus::tryFrom((int)$row['status']),
            'siblings' => $this->mailLogRepository->findSiblings((string)$row['submission_id'], $uid),
            'stuckGraceMinutes' => (int)(MailLogRepository::STUCK_GRACE_SECONDS / 60),
        ]);

        return $moduleTemplate->renderResponse('Backend/MailLog/Show');
    }

    /**
     * Summary numbers for the active filter, keyed by a name the template can
     * read. "Unknown" deliberately merges PENDING and PREPARED: the difference
     * matters when diagnosing one row, not when counting how many need a look.
     *
     * @return array<string, int>
     */
    protected function counts(MailLogDemand $demand): array
    {
        $byStatus = $this->mailLogRepository->countByStatus($demand);

        return [
            'sent' => $byStatus[MailLogStatus::SENT->value] ?? 0,
            'failed' => $byStatus[MailLogStatus::FAILED->value] ?? 0,
            'open' => ($byStatus[MailLogStatus::PENDING->value] ?? 0)
                + ($byStatus[MailLogStatus::PREPARED->value] ?? 0),
            'total' => array_sum($byStatus),
        ];
    }

    /**
     * @return array<string, string> filter value => label
     */
    protected function formOptions(): array
    {
        $options = ['' => $this->translate('mailLog.filter.form.all')];
        foreach ($this->mailLogRepository->findDistinctFormIdentifiers(time() - self::FILTER_OPTIONS_DAYS * 86400) as $identifier) {
            $options[$identifier] = $identifier;
        }

        return $options;
    }

    /**
     * @return array<string, string> filter value => label
     */
    protected function statusOptions(): array
    {
        $options = [
            '' => $this->translate('mailLog.filter.status.all'),
            MailLogDemand::STATUS_PROBLEMS => $this->translate('mailLog.filter.status.problems'),
        ];
        foreach (MailLogStatus::cases() as $case) {
            $options[(string)$case->value] = $this->getLanguageService()->sL($case->label());
        }

        return $options;
    }

    protected function initializeModuleTemplate(
        ServerRequestInterface $request,
        int $page,
        MailLogDemand $demand,
    ): ModuleTemplate {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $arguments = ['filter' => $demand->toArray()];
        if ($page > 1) {
            $arguments['page'] = $page;
        }
        $moduleTemplate->getDocHeaderComponent()->setShortcutContext(
            'form_log',
            $this->translate('mailLog.headline'),
            // The plugin namespace Extbase derives from the module identifier —
            // not the container's, which is the mistake in FormManagerController.
            ['tx_form_form_log' => $arguments]
        );

        $moduleTemplate->setModuleClass($this->request->getPluginName() . '_' . $this->request->getControllerName());
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        $moduleTemplate->setTitle($this->translate('mailLog.headline'));

        return $moduleTemplate;
    }

    protected function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:form/Resources/Private/Language/Database.xlf:' . $key
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
