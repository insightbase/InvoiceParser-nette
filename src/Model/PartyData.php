<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\Model;

final class PartyData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $ico = null,
        public readonly ?string $dic = null,
        public readonly ?string $address = null,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'address' => $this->address,
        ];
    }
}
