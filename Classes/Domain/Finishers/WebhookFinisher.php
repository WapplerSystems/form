<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Event\AfterWebhookResponseEvent;
use TYPO3\CMS\Form\Event\BeforeWebhookRequestEvent;
use TYPO3\CMS\Form\Service\SubmissionValueSerializer;

/**
 * Sends the submission to an arbitrary HTTP endpoint (n8n, Zapier, Make, a
 * CRM, …) as JSON or form-encoded data. This is the fork's first outbound HTTP
 * finisher and the generic integration bridge that complements provider
 * specific finishers.
 *
 * Options:
 *   url            - target URL (mandatory; supports {fieldId} placeholders).
 *   method         - POST (default), PUT or PATCH.
 *   format         - json (default) or form (application/x-www-form-urlencoded).
 *   payloadMode    - 'all' (default: every submitted value under `values`) or
 *                    'custom' (only the mapped keys from `fieldMapping`).
 *   fieldMapping   - map of payloadKey => "template with {fieldId} placeholders".
 *   includeMetadata- prepend formIdentifier/formLabel/submittedAt/pageUid (default true).
 *   includeUploads - include uploaded-file references in `all` mode (default true).
 *   headers        - map of static request headers (values may use placeholders).
 *   authType       - none (default), bearer or basic.
 *   authToken      - bearer token.
 *   authUser/authPassword - basic-auth credentials.
 *   hmacSecret     - when set, the raw body is signed and sent in `signatureHeader`.
 *   signatureHeader- header name for the HMAC signature (default X-Form-Signature).
 *   timeout        - request timeout in seconds (default 10).
 *   retries        - additional attempts after the first on failure (default 2).
 *   retryDelayMs   - delay between attempts in milliseconds (default 500).
 *   failOnError    - when true, a persistent failure cancels the finisher chain
 *                    and shows the form error message; otherwise it is logged
 *                    and the submission proceeds (default false).
 */
class WebhookFinisher extends AbstractFinisher
{
    private const LOG_TABLE = 'tx_form_webhook_log';

    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'url' => '',
        'method' => 'POST',
        'format' => 'json',
        'payloadMode' => 'all',
        'includeMetadata' => true,
        'includeUploads' => true,
        'authType' => 'none',
        'authToken' => '',
        'authUser' => '',
        'authPassword' => '',
        'hmacSecret' => '',
        'signatureHeader' => 'X-Form-Signature',
        'timeout' => 10,
        'retries' => 2,
        'retryDelayMs' => 500,
        'failOnError' => false,
    ];

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConnectionPool $connectionPool,
        private readonly SubmissionValueSerializer $serializer,
    ) {}

    protected function executeInternal(): void
    {
        $url = trim((string)$this->parseOption('url'));
        if ($url === '') {
            throw new FinisherException('The WebhookFinisher requires a non-empty "url" option.', 1720000001);
        }

        $method = strtoupper((string)$this->parseOption('method')) ?: 'POST';
        $format = strtolower((string)$this->parseOption('format')) ?: 'json';

        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $payload = $this->buildPayload($format);
        $headers = $this->buildHeaders($format);

        $event = $this->eventDispatcher->dispatch(
            new BeforeWebhookRequestEvent($url, $payload, $headers, $this->finisherContext, $this)
        );
        $url = $event->getUrl();
        $payload = $event->getPayload();
        $headers = $event->getHeaders();

        $body = $format === 'form'
            ? http_build_query($payload)
            : (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $hmacSecret = (string)$this->parseOption('hmacSecret');
        if ($hmacSecret !== '') {
            $signatureHeader = (string)$this->parseOption('signatureHeader') ?: 'X-Form-Signature';
            $headers[$signatureHeader] = 'sha256=' . hash_hmac('sha256', $body, $hmacSecret);
        }

        $timeout = max(1, (int)$this->parseOption('timeout'));
        $retries = max(0, (int)$this->parseOption('retries'));
        $retryDelayMs = max(0, (int)$this->parseOption('retryDelayMs'));

        $attempts = 0;
        $statusCode = 0;
        $responseBody = '';
        $success = false;
        $lastError = '';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $attempts = $attempt + 1;
            try {
                $response = $this->requestFactory->request($url, $method, [
                    'headers' => $headers,
                    'body' => $body,
                    'timeout' => $timeout,
                    'http_errors' => false,
                    'allow_redirects' => true,
                ]);
                $statusCode = $response->getStatusCode();
                $responseBody = (string)$response->getBody()->getContents();
                $success = $statusCode >= 200 && $statusCode < 400;
                if ($success) {
                    break;
                }
                $lastError = 'HTTP ' . $statusCode;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < $retries && $retryDelayMs > 0) {
                usleep($retryDelayMs * 1000);
            }
        }

        $this->eventDispatcher->dispatch(
            new AfterWebhookResponseEvent($url, $statusCode, $responseBody, $attempts, $success, $this->finisherContext, $this)
        );

        $this->writeLog($formDefinition->getIdentifier(), $url, $method, $statusCode, $attempts, $success, $responseBody);

        if (!$success) {
            $this->logger?->warning('Webhook delivery failed', [
                'url' => $url,
                'form' => $formDefinition->getIdentifier(),
                'attempts' => $attempts,
                'error' => $lastError,
            ]);
            if ($this->toBool($this->parseOption('failOnError'))) {
                throw new FinisherException(
                    sprintf('Webhook delivery to "%s" failed after %d attempt(s): %s', $url, $attempts, $lastError),
                    1720000002
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $format): array
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $custom = strtolower((string)$this->parseOption('payloadMode')) === 'custom';
        $includeUploads = $this->toBool($this->parseOption('includeUploads'));

        if ($custom) {
            $payload = [];
            $mapping = is_array($this->options['fieldMapping'] ?? null) ? $this->options['fieldMapping'] : [];
            foreach ($mapping as $key => $template) {
                $payload[(string)$key] = is_string($template)
                    ? $this->substituteRuntimeReferences($template, $formRuntime)
                    : $template;
            }
        } else {
            $payload = [
                'values' => $this->serializer->serializeValues(
                    $this->finisherContext->getFormValues(),
                    $includeUploads,
                ),
            ];
        }

        if ($this->toBool($this->parseOption('includeMetadata'))) {
            $pageUid = 0;
            $routing = $this->finisherContext->getRequest()->getAttribute('routing');
            if ($routing !== null && method_exists($routing, 'getPageId')) {
                $pageUid = (int)$routing->getPageId();
            }
            $meta = [
                'formIdentifier' => $formDefinition->getIdentifier(),
                'formLabel' => (string)$formDefinition->getLabel(),
                'submittedAt' => date(\DateTimeInterface::ATOM),
                'pageUid' => $pageUid,
            ];
            $payload = array_merge($meta, $payload);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $format): array
    {
        $headers = [];
        $formRuntime = $this->finisherContext->getFormRuntime();

        $static = is_array($this->options['headers'] ?? null) ? $this->options['headers'] : [];
        foreach ($static as $name => $value) {
            if (is_string($value)) {
                $headers[(string)$name] = $this->substituteRuntimeReferences($value, $formRuntime);
            }
        }

        $headers['Content-Type'] = $format === 'form'
            ? 'application/x-www-form-urlencoded'
            : 'application/json';

        $authType = strtolower((string)$this->parseOption('authType'));
        if ($authType === 'bearer') {
            $token = (string)$this->parseOption('authToken');
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        } elseif ($authType === 'basic') {
            $user = (string)$this->parseOption('authUser');
            $password = (string)$this->parseOption('authPassword');
            if ($user !== '') {
                $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $password);
            }
        }

        return $headers;
    }

    private function writeLog(
        string $formIdentifier,
        string $url,
        string $method,
        int $statusCode,
        int $attempts,
        bool $success,
        string $responseBody,
    ): void {
        try {
            $this->connectionPool->getConnectionForTable(self::LOG_TABLE)->insert(
                self::LOG_TABLE,
                [
                    'crdate' => (int)($GLOBALS['EXEC_TIME'] ?? time()),
                    'form_identifier' => $formIdentifier,
                    'url' => mb_substr($url, 0, 2048),
                    'http_method' => $method,
                    'status_code' => $statusCode,
                    'attempts' => $attempts,
                    'success' => $success ? 1 : 0,
                    'response_excerpt' => mb_substr($responseBody, 0, 1000),
                ],
            );
        } catch (\Throwable $e) {
            // Logging must never break the submission flow.
            $this->logger?->notice('Could not write webhook log row', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Casts an option to bool while treating the FlexForm/string '0' as false
     * (a plain (bool) cast would turn '0' into true).
     */
    private function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'false', 'no', 'off'], true);
        }
        return (bool)$value;
    }
}
