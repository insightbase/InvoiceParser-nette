<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Parser;

final class ParsedDocument
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly array $fields,
        public readonly array $raw = [],
    ) {
    }
}
