<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

interface DocumentAnalyzerInterface
{
    public function analyzePdf(string $pdfContent): ParsedDocument;
}
