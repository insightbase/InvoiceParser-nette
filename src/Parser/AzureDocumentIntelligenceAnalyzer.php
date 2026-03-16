<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class AzureDocumentIntelligenceAnalyzer implements DocumentAnalyzerInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly string $model = 'prebuilt-invoice',
        private readonly string $apiVersion = '2023-07-31',
        private readonly int $maxPollAttempts = 25,
        private readonly int $pollIntervalMs = 1000,
    ) {
    }

    public function analyzePdf(string $pdfContent): ParsedDocument
    {
        try {
            $response = $this->httpClient->request('POST', $this->buildAnalyzeUrl(), [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    'Content-Type' => 'application/pdf',
                ],
                'body' => $pdfContent,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Azure DI analyze request failed.', previous: $e);
        }

        $operationLocation = $response->getHeaderLine('Operation-Location');
        if ($operationLocation === '') {
            throw new RuntimeException('Azure DI did not return Operation-Location header.');
        }

        $analysis = $this->pollResult($operationLocation);
        $analyzeResult = (array) ($analysis['analyzeResult'] ?? []);
        $content = (string) ($analyzeResult['content'] ?? '');
        $documents = (array) ($analyzeResult['documents'] ?? []);
        $firstDocument = (array) ($documents[0] ?? []);
        $fields = $this->normalizeFields((array) ($firstDocument['fields'] ?? []));

        return new ParsedDocument($content, $fields, $analysis);
    }

    private function buildAnalyzeUrl(): string
    {
        $endpoint = rtrim($this->endpoint, '/');
        $servicePath = $this->resolveServicePath();
        return sprintf(
            '%s/%s/documentModels/%s:analyze?api-version=%s',
            $endpoint,
            $servicePath,
            rawurlencode($this->model),
            rawurlencode($this->apiVersion),
        );
    }

    private function resolveServicePath(): string
    {
        $parsedPath = trim((string) parse_url($this->endpoint, PHP_URL_PATH), '/');
        if ($parsedPath === 'documentintelligence' || $parsedPath === 'formrecognizer') {
            return $parsedPath;
        }

        // Document Intelligence v4+ uses /documentintelligence, older APIs use /formrecognizer.
        return $this->isDocumentIntelligenceV4Api() ? 'documentintelligence' : 'formrecognizer';
    }

    private function isDocumentIntelligenceV4Api(): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $this->apiVersion, $matches) !== 1) {
            return false;
        }

        $numericDate = ((int) $matches[1]) * 10000 + ((int) $matches[2]) * 100 + ((int) $matches[3]);
        return $numericDate >= 20241130;
    }

    /**
     * @return array<string, mixed>
     */
    private function pollResult(string $operationLocation): array
    {
        for ($attempt = 0; $attempt < $this->maxPollAttempts; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $operationLocation, [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    ],
                ]);
            } catch (GuzzleException $e) {
                throw new RuntimeException('Azure DI polling request failed.', previous: $e);
            }

            $body = (string) $response->getBody();
            /** @var array<string, mixed> $json */
            $json = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $status = strtolower((string) ($json['status'] ?? ''));

            if ($status === 'succeeded') {
                return $json;
            }

            if ($status === 'failed') {
                throw new RuntimeException('Azure DI analysis failed.');
            }

            usleep($this->pollIntervalMs * 1000);
        }

        throw new RuntimeException('Azure DI polling timed out.');
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $name => $field) {
            $normalized[$name] = $this->extractFieldValue((array) $field);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $field
     * @return mixed
     */
    private function extractFieldValue(array $field): mixed
    {
        if (array_key_exists('valueString', $field)) {
            return $field['valueString'];
        }
        if (array_key_exists('valueNumber', $field)) {
            return $field['valueNumber'];
        }
        if (array_key_exists('valueDate', $field)) {
            return $field['valueDate'];
        }
        if (array_key_exists('valueCurrency', $field)) {
            $currency = (array) $field['valueCurrency'];
            return $currency['amount'] ?? null;
        }
        if (($field['type'] ?? null) === 'array') {
            $result = [];
            foreach ((array) ($field['valueArray'] ?? []) as $value) {
                $result[] = $this->extractFieldValue((array) $value);
            }
            return $result;
        }
        if (($field['type'] ?? null) === 'object') {
            $result = [];
            foreach ((array) ($field['valueObject'] ?? []) as $key => $value) {
                $result[$key] = $this->extractFieldValue((array) $value);
            }
            return $result;
        }

        return $field['content'] ?? null;
    }
}
