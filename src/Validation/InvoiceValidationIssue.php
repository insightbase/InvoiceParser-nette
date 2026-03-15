<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Validation;

final class InvoiceValidationIssue
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}
