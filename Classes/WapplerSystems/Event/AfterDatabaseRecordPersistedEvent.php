<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\Event;

use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;

/**
 * Dispatched from SaveToDatabaseFinisher::saveToDatabase() right after a row was
 * inserted or updated — and therefore also for FeUserFinisher (which extends
 * SaveToDatabaseFinisher).
 *
 * The reliable hook for "a record was persisted by a form": trigger a workflow
 * on a new fe_user, push the row to a CRM, enrich related records, etc. — with
 * the freshly inserted uid in hand instead of having to scrape the finisher
 * variable provider.
 *
 * For `mode: update` there is no single new uid, so `$uid` is 0; inspect
 * `$mode` and `$data` (the written columns) to react accordingly.
 */
final class AfterDatabaseRecordPersistedEvent
{
    /**
     * @param array<string, mixed> $data the column values written to the row
     * @param 'insert'|'update' $mode
     */
    public function __construct(
        public readonly string $table,
        public readonly int $uid,
        public readonly array $data,
        public readonly string $mode,
        public readonly FinisherInterface $finisher,
        public readonly FinisherContext $finisherContext,
    ) {}
}
