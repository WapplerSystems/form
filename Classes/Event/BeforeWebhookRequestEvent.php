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
 * Dispatched by the WebhookFinisher immediately before the outbound HTTP
 * request is sent. Listeners can rewrite the target URL, mutate the payload
 * (e.g. enrich with CRM ids, drop fields) and add/override headers. The final
 * body — and, when configured, its HMAC signature — is computed from the
 * values as they stand after this event.
 */
final class BeforeWebhookRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $url,
        private array $payload,
        private array $headers,
        public readonly FinisherContext $finisherContext,
        public readonly WebhookFinisher $finisher,
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
}
