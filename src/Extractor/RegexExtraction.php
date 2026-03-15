<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Extractor;

use DateTimeImmutable;

final class RegexExtraction
{
    public function __construct(
        public readonly ?string $variableSymbol = null,
        public readonly ?string $supplierIco = null,
        public readonly ?string $supplierDic = null,
        public readonly ?DateTimeImmutable $issuedAt = null,
        public readonly ?DateTimeImmutable $taxableSupplyDate = null,
        public readonly ?DateTimeImmutable $dueDate = null,
    ) {
    }
}
