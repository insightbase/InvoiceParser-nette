<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Model;

use DateTimeInterface;

final class InvoiceData
{
    /**
     * @param list<InvoiceItemData> $items
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $variableSymbol = null,
        public readonly ?string $currency = null,
        public readonly ?PartyData $supplier = null,
        public readonly ?PartyData $customer = null,
        public readonly ?DateTimeInterface $issuedAt = null,
        public readonly ?DateTimeInterface $taxableSupplyDate = null,
        public readonly ?DateTimeInterface $dueDate = null,
        public readonly ?float $totalWithoutVat = null,
        public readonly ?float $totalVat = null,
        public readonly ?float $totalWithVat = null,
        public readonly array $items = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoiceNumber' => $this->invoiceNumber,
            'variableSymbol' => $this->variableSymbol,
            'currency' => $this->currency,
            'supplier' => $this->supplier?->toArray(),
            'customer' => $this->customer?->toArray(),
            'issuedAt' => $this->issuedAt?->format('Y-m-d'),
            'taxableSupplyDate' => $this->taxableSupplyDate?->format('Y-m-d'),
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'totalWithoutVat' => $this->totalWithoutVat,
            'totalVat' => $this->totalVat,
            'totalWithVat' => $this->totalWithVat,
            'items' => array_map(static fn (InvoiceItemData $item): array => $item->toArray(), $this->items),
            'metadata' => $this->metadata,
        ];
    }
}
