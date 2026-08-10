<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Event;

use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\WebhookFinisher;

/**
 * Dispatched by the WebhookFinisher after a delivery attempt returned a
 * response (i.e. not on a transport error, which is retried). `$success`
 * reflects a 2xx/3xx status. Useful for conversion tracking, storing the
 * remote id from the response body, or triggering follow-up actions.
 */
final class AfterWebhookResponseEvent
{
    public function __construct(
        public readonly string $url,
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly int $attempts,
        public readonly bool $success,
        public readonly FinisherContext $finisherContext,
        public readonly WebhookFinisher $finisher,
    ) {}
}
