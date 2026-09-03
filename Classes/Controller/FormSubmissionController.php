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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Form\Service\SubmissionExportService;

/**
 * Backend module to review, search, export and delete persisted form
 * submissions (table tx_form_submission) written by the SaveSubmission
 * finisher.
 *
 * Scope: backend
 * @internal
 */
class FormSubmissionController extends ActionController
{
    use AllowedMethodsTrait;
    // A GET filter form drops the request token from its action's query string;
    // without this the module frame renders the whole backend instead of the
    // filtered list. Same helper the form log views use.
    use FilterFormTargetTrait;

    private const TABLE_NAME = 'tx_form_submission';
    private const PAGINATION_MAX = 25;
    private const LIST_HARD_LIMIT = 5000;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly ConnectionPool $connectionPool,
        protected readonly SubmissionExportService $exportService,
    ) {}

    protected function indexAction(
        string $form = '',
        int $page = 1,
        string $searchTerm = '',
        string $dateFrom = '',
        string $dateTo = '',
    ): ResponseInterface {
        $forms = $this->getFormOverview();
        $selectOptions = [];
        foreach ($forms as $formItem) {
            $selectOptions[$formItem['identifier']] = $formItem['label'] . ' (' . $formItem['count'] . ')';
        }

        $rows = [];
        $columns = [];
        $truncated = false;
        if ($form !== '') {
            [$rows, $columns, $truncated] = $this->getSubmissions($form, $searchTerm, $dateFrom, $dateTo);
        }

        $paginator = new ArrayPaginator($rows, $page, self::PAGINATION_MAX);
        $pagination = new SimplePagination($paginator);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'forms' => $forms,
            'filterForm' => $this->buildFilterFormTarget(),
            'selectOptions' => $selectOptions,
            'selectedForm' => $form,
            'columns' => $columns,
            'paginator' => $paginator,
            'pagination' => $pagination,
            'searchTerm' => $searchTerm,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'truncated' => $truncated,
            'totalCount' => count($rows),
        ]);
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:module.title')
        );
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        return $moduleTemplate->renderResponse('Backend/FormSubmission/Index');
    }

    protected function showAction(int $submission): ResponseInterface
    {
        $row = $this->findSubmission($submission);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ($row === null) {
            $moduleTemplate->addFlashMessage(
                $this->getLanguageService()->sL('LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:module.notFound'),
                '',
                \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('index');
        }
        $labels = $this->decodeJson($row['field_labels'] ?? '');
        $values = $this->decodeJson($row['content'] ?? '');
        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs[] = [
                'label' => $labels[$key] ?? $key,
                'identifier' => $key,
                'value' => $this->stringifyValue($value),
            ];
        }
        $moduleTemplate->assignMultiple([
            'submission' => $row,
            'pairs' => $pairs,
        ]);
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:form/Resources/Private/Language/locallang_submission.xlf:module.title')
        );
        return $moduleTemplate->renderResponse('Backend/FormSubmission/Show');
    }

    protected function initializeDeleteAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    protected function deleteAction(int $submission, string $form = ''): ResponseInterface
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $connection->delete(self::TABLE_NAME, ['uid' => $submission]);
        return $this->redirect('index', null, null, $form !== '' ? ['form' => $form] : []);
    }

    protected function exportAction(
        string $form,
        string $format = 'csv',
        string $searchTerm = '',
        string $dateFrom = '',
        string $dateTo = '',
    ): ResponseInterface {
        [$rows, $columns] = $this->getSubmissions($form, $searchTerm, $dateFrom, $dateTo);
        return $this->exportService->export($format, $form, $columns, $rows, $this->request);
    }

    /**
     * @return list<array{identifier: string, label: string, count: int}>
     */
    private function getFormOverview(): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder
            ->select('form_identifier')
            ->addSelectLiteral(
                'MAX(' . $queryBuilder->quoteIdentifier('form_label') . ') AS form_label',
                'COUNT(' . $queryBuilder->quoteIdentifier('uid') . ') AS cnt'
            )
            ->from(self::TABLE_NAME)
            ->groupBy('form_identifier')
            ->orderBy('form_label', 'ASC')
            ->executeQuery();

        $forms = [];
        while ($record = $result->fetchAssociative()) {
            $forms[] = [
                'identifier' => (string)$record['form_identifier'],
                'label' => (string)($record['form_label'] ?: $record['form_identifier']),
                'count' => (int)$record['cnt'],
            ];
        }
        return $forms;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>, 2: bool}
     */
    private function getSubmissions(string $form, string $searchTerm, string $dateFrom, string $dateTo): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('form_identifier', $queryBuilder->createNamedParameter($form)))
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(self::LIST_HARD_LIMIT + 1);

        $this->applyDateFilter($queryBuilder, $dateFrom, $dateTo);
        if ($searchTerm !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->like(
                    'content',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($searchTerm) . '%')
                )
            );
        }

        $records = $queryBuilder->executeQuery()->fetchAllAssociative();
        $truncated = count($records) > self::LIST_HARD_LIMIT;
        if ($truncated) {
            $records = array_slice($records, 0, self::LIST_HARD_LIMIT);
        }

        $columns = [];
        $rows = [];
        foreach ($records as $record) {
            $labels = $this->decodeJson($record['field_labels'] ?? '');
            $values = $this->decodeJson($record['content'] ?? '');
            foreach ($labels as $key => $label) {
                if (!isset($columns[$key])) {
                    $columns[$key] = (string)($label ?: $key);
                }
            }
            foreach (array_keys($values) as $key) {
                if (!isset($columns[$key])) {
                    $columns[$key] = (string)$key;
                }
            }
            $cells = [];
            foreach ($values as $key => $value) {
                $cells[$key] = $this->stringifyValue($value);
            }
            $rows[] = [
                'uid' => (int)$record['uid'],
                'crdate' => (int)$record['crdate'],
                'language_uid' => (int)$record['language_uid'],
                'cells' => $cells,
            ];
        }

        return [$rows, $columns, $truncated];
    }

    private function applyDateFilter(QueryBuilder $queryBuilder, string $dateFrom, string $dateTo): void
    {
        if ($dateFrom !== '' && ($from = strtotime($dateFrom . ' 00:00:00')) !== false) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($from, Connection::PARAM_INT))
            );
        }
        if ($dateTo !== '' && ($to = strtotime($dateTo . ' 23:59:59')) !== false) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte('crdate', $queryBuilder->createNamedParameter($to, Connection::PARAM_INT))
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSubmission(int $uid): ?array
    {
        $queryBuilder = $this->getQueryBuilder();
        $record = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $record === false ? null : $record;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        return $queryBuilder;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            // File descriptor {uid, identifier, name}
            if (isset($value['name']) && isset($value['identifier'])) {
                return (string)$value['name'];
            }
            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->stringifyValue($item);
            }
            return implode(', ', $parts);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
