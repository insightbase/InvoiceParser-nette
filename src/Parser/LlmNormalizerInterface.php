<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

interface LlmNormalizerInterface
{
    /**
     * @param array<string, mixed> $candidateData
     * @return array<string, mixed>
     */
    public function normalize(string $documentText, array $candidateData): array;
}
