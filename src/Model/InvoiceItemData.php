<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Model;

final class InvoiceItemData
{
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $total = null,
        public readonly ?float $vatRate = null,
    ) {
    }

    /**
     * @return array<string, float|string|null>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'total' => $this->total,
            'vatRate' => $this->vatRate,
        ];
    }
}
