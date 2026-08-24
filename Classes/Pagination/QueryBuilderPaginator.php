<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Pagination;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Pagination\AbstractPaginator;

/**
 * Paginates a Doctrine query builder, one page of rows per request.
 *
 * TYPO3 v14 ships this as \TYPO3\CMS\Core\Pagination\QueryBuilderPaginator;
 * v13.4 does not, and the log modules would otherwise have to read the whole
 * table into an ArrayPaginator to show twenty rows. Same constructor and same
 * behaviour as the v14 class, so the v14 branch can keep using core's.
 *
 * @internal not part of public TYPO3 Core API
 */
final class QueryBuilderPaginator extends AbstractPaginator
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $paginatedItems = [];

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        int $currentPageNumber = 1,
        int $itemsPerPage = 10,
    ) {
        $this->setCurrentPageNumber($currentPageNumber);
        $this->setItemsPerPage($itemsPerPage);

        $this->updateInternalState();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPaginatedItems(): iterable
    {
        return $this->paginatedItems;
    }

    protected function updatePaginatedItems(int $itemsPerPage, int $offset): void
    {
        $queryBuilder = clone $this->queryBuilder;
        $this->paginatedItems = $queryBuilder
            ->setMaxResults($itemsPerPage)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function getTotalAmountOfItems(): int
    {
        // The ORDER BY has to go: with COUNT() and no GROUP BY, a server running
        // ONLY_FULL_GROUP_BY rejects ordering by a column that is not aggregated.
        $queryBuilder = clone $this->queryBuilder;
        $queryBuilder->resetOrderBy();
        $queryBuilder->setMaxResults(null);
        $queryBuilder->setFirstResult(0);

        return (int)$queryBuilder
            ->count('*')
            ->executeQuery()
            ->fetchOne();
    }

    protected function getAmountOfItemsOnCurrentPage(): int
    {
        return count($this->paginatedItems);
    }
}
