<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

use InsightBase\InvoiceParserNette\Model\InvoiceData;
use InsightBase\InvoiceParserNette\Model\ParseWarning;

final class ParseResult
{
    /**
     * @param list<ParseWarning> $warnings
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly InvoiceData $invoice,
        public readonly array $warnings = [],
        public readonly array $raw = [],
    ) {
    }
}
