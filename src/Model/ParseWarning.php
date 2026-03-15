<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Model;

final class ParseWarning
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}
