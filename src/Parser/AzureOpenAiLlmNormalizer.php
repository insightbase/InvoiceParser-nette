<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class AzureOpenAiLlmNormalizer implements LlmNormalizerInterface
{
    private const DEFAULT_SYSTEM_PROMPT = <<<PROMPT
You normalize Czech and Slovak invoices to JSON.
Return valid JSON object only with these keys:
invoiceNumber, variableSymbol, currency, issuedAt, taxableSupplyDate, dueDate,
supplier{name,ico,dic,address}, customer{name,ico,dic,address},
totalWithoutVat, totalVat, totalWithVat,
items[{description,quantity,unitPrice,total,vatRate}].
If unknown, use null.
PROMPT;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $endpoint,
        private readonly string $deployment,
        private readonly string $apiKey,
        private readonly string $apiVersion = '2024-10-21',
        private readonly string $systemPrompt = self::DEFAULT_SYSTEM_PROMPT,
    ) {
    }

    /**
     * @param array<string, mixed> $candidateData
     * @return array<string, mixed>
     */
    public function normalize(string $documentText, array $candidateData): array
    {
        $payload = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'candidate' => $candidateData,
                        'documentText' => mb_substr($documentText, 0, 20000),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.0,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api-key' => $this->apiKey,
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Azure OpenAI normalization request failed.', previous: $e);
        }

        /** @var array<string, mixed> $json */
        $json = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $content = (string) ($json['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
            return [];
        }

        $extracted = json_decode($matches[0], true);
        return is_array($extracted) ? $extracted : [];
    }

    private function buildUrl(): string
    {
        return sprintf(
            '%s/openai/deployments/%s/chat/completions?api-version=%s',
            rtrim($this->endpoint, '/'),
            rawurlencode($this->deployment),
            rawurlencode($this->apiVersion),
        );
    }
}
