# insightbase/invoice-parser-nette

Nette balíček pro vytěžování faktur a účetních dokladů z PDF (včetně skenů) přes:

- Azure Document Intelligence (OCR + strukturovaná extrakce)
- LLM normalizaci (Azure OpenAI)
- české regex fallbacky (`VS`, `DUZP`, `IČO`, `DIČ`)
- validační vrstvu a asynchronní worker pattern

## Instalace

```bash
composer require insightbase/invoice-parser-nette
```

## Registrace extension

```neon
extensions:
    invoiceParser: InsightBase\InvoiceParserNette\DI\InvoiceParserExtension

invoiceParser:
    azureDi:
        endpoint: %env(AZURE_DI_ENDPOINT)%
        apiKey: %env(AZURE_DI_KEY)%
        model: prebuilt-invoice
        apiVersion: 2023-07-31
        maxPollAttempts: 25
        pollIntervalMs: 1000
    llm:
        enabled: true
        endpoint: %env(AZURE_OPENAI_ENDPOINT)%
        deployment: %env(AZURE_OPENAI_DEPLOYMENT)%
        apiKey: %env(AZURE_OPENAI_KEY)%
        apiVersion: 2024-10-21
```

## Použití

```php
<?php

declare(strict_types=1);

use InsightBase\InvoiceParserNette\Parser\InvoiceParser;

final class InvoiceService
{
    public function __construct(
        private InvoiceParser $invoiceParser,
    ) {
    }

    public function parse(string $pdfPath): array
    {
        $pdfContent = file_get_contents($pdfPath);
        $result = $this->invoiceParser->parsePdf((string) $pdfContent);

        return $result->invoice->toArray();
    }
}
```

## Asynchronní worker (Contributte RabbitMQ)

Knihovna obsahuje worker service `InvoiceParseWorker::process(array $message)`.

Příklad payloadu zprávy:

```json
{
  "pdfPath": "/data/invoices/invoice-2026-001.pdf"
}
```

Nebo:

```json
{
  "pdfBase64": "JVBERi0xLjQKJ..."
}
```

Ukázková integrace je v [examples/rabbitmq.neon](examples/rabbitmq.neon) a [examples/InvoiceConsumer.php](examples/InvoiceConsumer.php).

## Poznámky

- Pro oskenované PDF se OCR řeší na straně Azure Document Intelligence.
- Regex fallback slouží jako doplněk, když DI/LLM vrátí neúplná data.
- Validátor hlídá základní konzistenci částek a dat.
