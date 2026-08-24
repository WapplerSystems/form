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
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Pagination\QueryBuilderPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Form\Domain\DTO\ConsentLogDemand;
use TYPO3\CMS\Form\Domain\Repository\ConsentLogRepository;

/**
 * Backend module listing the consent log.
 *
 * A log nobody but a database client can read does not satisfy "shall be able
 * to demonstrate" in any practical sense — the person who has to produce the
 * record is a data protection officer answering a subject access request, not
 * someone with SQL on production. Hence the subject search: type an address,
 * get every consent that person ever gave, with the wording they were shown.
 *
 * @internal not part of public TYPO3 Core API
 */
class ConsentLogController extends AbstractFormLogController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ComponentFactory $componentFactory,
        IconFactory $iconFactory,
        protected readonly ConsentLogRepository $consentLogRepository,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct($moduleTemplateFactory, $componentFactory, $iconFactory);
    }

    /**
     * @param array<string, mixed> $filter
     */
    protected function indexAction(int $page = 1, array $filter = []): ResponseInterface
    {
        $demand = ConsentLogDemand::fromArray($filter);

        $paginator = new QueryBuilderPaginator(
            $this->consentLogRepository->createDemandQueryBuilder($demand),
            max(1, $page),
            self::PAGINATION_MAX,
        );

        $arguments = ['filter' => $demand->toArray()];
        if ($page > 1) {
            $arguments['page'] = $page;
        }
        $moduleTemplate = $this->createModuleTemplate($this->request, 'consentLog.headline', $arguments);
        $moduleTemplate->assignMultiple([
            'paginator' => $paginator,
            'pagination' => new SimplePagination($paginator),
            'filterForm' => $this->buildFilterFormTarget(),
            'filter' => $demand->toArray(),
            'demand' => $demand,
            'total' => $this->consentLogRepository->countByDemand($demand),
            'formOptions' => $this->formOptions(),
            'givenOptions' => $this->givenOptions(),
            'texts' => $this->consentLogRepository->findAllTexts(),
            // A disabled feature and an empty log look identical in the
            // listing, and the difference is the one thing a reader needs.
            'featureEnabled' => $this->featureEnabled(),
        ]);

        return $moduleTemplate->renderResponse('Backend/ConsentLog/Index');
    }

    protected function showAction(int $uid): ResponseInterface
    {
        $row = $this->consentLogRepository->findByUid($uid);
        if ($row === null) {
            return $this->redirect('index');
        }

        $moduleTemplate = $this->createModuleTemplate($this->request, 'consentLog.headline');
        $moduleTemplate->addButtonToButtonBar(
            $this->componentFactory->createLinkButton()
                ->setHref($this->uriBuilder->uriFor('index'))
                ->setTitle($this->translate('consentLog.back'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL))
        );
        $moduleTemplate->assignMultiple([
            'entry' => $row,
            'consentText' => $this->consentLogRepository->findTextByHash((string)$row['text_hash']),
            'siblings' => $this->consentLogRepository->findSiblings((string)$row['submission_id'], $uid),
        ]);

        return $moduleTemplate->renderResponse('Backend/ConsentLog/Show');
    }

    /**
     * @return array<string, string> filter value => label
     */
    protected function formOptions(): array
    {
        $options = ['' => $this->translate('consentLog.filter.form.all')];
        foreach ($this->consentLogRepository->findDistinctFormIdentifiers(time() - self::FILTER_OPTIONS_DAYS * 86400) as $identifier) {
            $options[$identifier] = $identifier;
        }

        return $options;
    }

    /**
     * @return array<string, string> filter value => label
     */
    protected function givenOptions(): array
    {
        return [
            '' => $this->translate('consentLog.filter.given.all'),
            '1' => $this->translate('consentLog.given'),
            '0' => $this->translate('consentLog.withheld'),
        ];
    }

    protected function featureEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', 'featureConsentLog');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }
    }
}
