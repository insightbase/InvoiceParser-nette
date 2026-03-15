<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

use InsightBase\InvoiceParserNette\Extractor\CzechRegexExtractor;
use InsightBase\InvoiceParserNette\Model\ParseWarning;
use InsightBase\InvoiceParserNette\Validation\InvoiceValidator;

final class InvoiceParser
{
    public function __construct(
        private readonly DocumentAnalyzerInterface $documentAnalyzer,
        private readonly CzechRegexExtractor $regexExtractor,
        private readonly InvoiceMapper $invoiceMapper,
        private readonly InvoiceValidator $validator,
        private readonly ?LlmNormalizerInterface $llmNormalizer = null,
    ) {
    }

    public function parsePdf(string $pdfContent): ParseResult
    {
        $parsedDocument = $this->documentAnalyzer->analyzePdf($pdfContent);
        $regexData = $this->regexExtractor->extract($parsedDocument->content);
        $llmData = [];

        if ($this->llmNormalizer !== null) {
            $llmData = $this->llmNormalizer->normalize($parsedDocument->content, $parsedDocument->fields);
        }

        $invoice = $this->invoiceMapper->map($parsedDocument->fields, $regexData, $llmData);
        $validation = $this->validator->validate($invoice);
        $warnings = array_map(
            static fn ($issue): ParseWarning => new ParseWarning($issue->code, $issue->message),
            $validation,
        );

        return new ParseResult(
            invoice: $invoice,
            warnings: $warnings,
            raw: [
                'document' => $parsedDocument->raw,
                'fields' => $parsedDocument->fields,
                'llm' => $llmData,
            ],
        );
    }
}
