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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Form\Domain\DTO\ValidationStatsDemand;
use TYPO3\CMS\Form\Domain\Repository\ValidationLogRepository;
use TYPO3\CMS\Form\Enum\ValidationAudience;

/**
 * Second view of the form log module: what visitors got wrong, and which of
 * them were not visitors.
 *
 * The reason this needs a bot filter rather than a plain list: on the site it
 * was built for, 16,096 recorded failures came from 141 sessions, and 129 of
 * those sessions were automated. The dozen real people in there — the ones whose
 * trouble is worth fixing — are 0.3 % of the rows. Without a filter the useful
 * signal is not merely diluted, it is invisible.
 *
 * @internal not part of public TYPO3 Core API
 */
class ValidationStatsController extends AbstractFormLogController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ComponentFactory $componentFactory,
        IconFactory $iconFactory,
        protected readonly ValidationLogRepository $validationLogRepository,
    ) {
        parent::__construct($moduleTemplateFactory, $componentFactory, $iconFactory);
    }

    /**
     * @param array<string, mixed> $filter
     */
    protected function indexAction(array $filter = []): ResponseInterface
    {
        // Default to the people rather than the noise. Anyone opening this view
        // wants to know what real visitors struggle with; the bot volume is
        // already summarised in the badges above the table.
        $filter['audience'] ??= ValidationAudience::HUMANS->value;
        $demand = ValidationStatsDemand::fromArray($filter);

        $moduleTemplate = $this->createModuleTemplate(
            $this->request,
            'validationStats.headline',
            ['filter' => $demand->toArray()]
        );

        $moduleTemplate->assignMultiple([
            'filterForm' => $this->buildFilterFormTarget(),
            'filter' => $demand->toArray(),
            'demand' => $demand,
            'sessions' => $this->validationLogRepository->countSessionsByAudience($demand),
            'rows' => $this->validationLogRepository->countRows($demand),
            'breakdown' => $this->validationLogRepository->findFailureBreakdown($demand),
            'daily' => $this->withShare($this->validationLogRepository->findDailyVolume($demand)),
            'busiest' => $this->validationLogRepository->findBusiestSessions($demand),
            'audiences' => $this->audienceOptions(),
            'formOptions' => $this->formOptions(),
            'botCodes' => implode(', ', ValidationAudience::conclusiveBotCodes()),
            'suspectedCodes' => implode(', ', ValidationAudience::suspectedBotCodes()),
        ]);

        return $moduleTemplate->renderResponse('Backend/ValidationStats/Index');
    }

    /**
     * Adds a 0-100 share per day so the template can draw a bar without doing
     * arithmetic in Fluid.
     *
     * @param list<array{day: string, hits: int, sessions: int}> $daily
     * @return list<array{day: string, hits: int, sessions: int, share: int}>
     */
    protected function withShare(array $daily): array
    {
        $peak = 0;
        foreach ($daily as $row) {
            $peak = max($peak, $row['hits']);
        }

        return array_map(
            static function (array $row) use ($peak): array {
                $row['share'] = $peak > 0 ? (int)round($row['hits'] / $peak * 100) : 0;
                return $row;
            },
            $daily
        );
    }

    /**
     * @return array<string, string>
     */
    protected function audienceOptions(): array
    {
        $options = [];
        foreach (ValidationAudience::cases() as $case) {
            $options[$case->value] = $this->getLanguageService()->sL($case->label());
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function formOptions(): array
    {
        $options = ['' => $this->translate('mailLog.filter.form.all')];
        foreach ($this->validationLogRepository->findDistinctFormIdentifiers(time() - self::FILTER_OPTIONS_DAYS * 86400) as $identifier) {
            $options[$identifier] = $identifier;
        }

        return $options;
    }
}
