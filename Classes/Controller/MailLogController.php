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
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Form\Pagination\QueryBuilderPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
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
class MailLogController extends AbstractFormLogController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        protected readonly MailLogRepository $mailLogRepository,
    ) {
        parent::__construct($moduleTemplateFactory, $iconFactory);
    }

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

        $arguments = ['filter' => $demand->toArray()];
        if ($page > 1) {
            $arguments['page'] = $page;
        }
        $moduleTemplate = $this->createModuleTemplate($this->request, 'mailLog.headline', $arguments);
        $moduleTemplate->assignMultiple([
            'paginator' => $paginator,
            'pagination' => new SimplePagination($paginator),
            'filterForm' => $this->buildFilterFormTarget(),
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

        $moduleTemplate = $this->createModuleTemplate($this->request, 'mailLog.headline');
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setHref($this->uriBuilder->uriFor('index'))
                ->setTitle($this->translate('mailLog.back'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL))
        );
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
}
