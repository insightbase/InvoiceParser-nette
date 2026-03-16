# insightbase/invoice-parser-nette

Nette balik pro vytezovani faktur a ucetnich dokladu z PDF (vcetne skenu) pres:

- Azure Document Intelligence (OCR + strukturovana extrakce)
- LLM normalizaci (Azure OpenAI)
- ceske regex fallbacky (`VS`, `DUZP`, `ICO`, `DIC`)
- validacni vrstvu a asynchronni worker pattern

## Instalace

```bash
composer require insightbase/invoice-parser-nette
```

## Azure setup (API key + endpoint + deployment)

Niz je doporuceny postup. Urceno pro stav k 15. 3. 2026.

### 1) Azure ucet a subscription

1. Vytvor nebo pouzij existujici Azure account.
2. Over, ze mas aktivni subscription a opravneni aspon `Contributor` na resource group.

### 2) Azure Document Intelligence (`azureDi`)

1. V Azure Portal vytvor resource typu `Document Intelligence` (historicky `Form Recognizer`).
2. Vyber region, kde sluzbu chces provozovat.
3. Po vytvoreni otevri `Keys and Endpoint`.
4. Zkopiruj:
- `Endpoint` -> pouzij jako `AZURE_DI_ENDPOINT`
- `Key 1` nebo `Key 2` -> pouzij jako `AZURE_DI_KEY`

### 3) Azure OpenAI (`llm`)

1. V Azure Portal vytvor resource `Azure OpenAI`.
2. V resource otevri `Keys and Endpoint`.
3. Zkopiruj:
- `Endpoint` -> `AZURE_OPENAI_ENDPOINT`
- `Key 1` nebo `Key 2` -> `AZURE_OPENAI_KEY`
4. Otevri Azure AI Foundry / model deployment panel pro tento resource.
5. Vytvor model deployment (napr. GPT model) a zapamatuj `deployment name` -> `AZURE_OPENAI_DEPLOYMENT`.

Poznamka:
- Pokud nejde Azure OpenAI resource nebo deployment vytvorit, jde obvykle o chybejici quota/permission v tenantu nebo regionu. V tom pripade je potreba pozadat Azure admina o povoleni.

### 4) Promenne prostredi

Minimalne nastav:

```dotenv
AZURE_DI_ENDPOINT=https://<your-di-resource>.cognitiveservices.azure.com
AZURE_DI_KEY=<your-di-key>
AZURE_OPENAI_ENDPOINT=https://<your-openai-resource>.openai.azure.com
AZURE_OPENAI_KEY=<your-openai-key>
AZURE_OPENAI_DEPLOYMENT=<your-deployment-name>
```

### 5) Konfigurace extension v Nette

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

### 6) Odkazy na oficialni dokumentaci

- Azure Document Intelligence quickstart:
  https://learn.microsoft.com/azure/ai-services/document-intelligence/quickstarts/get-started-sdks-rest-api
- Azure OpenAI chat completions quickstart:
  https://learn.microsoft.com/azure/ai-services/openai/chatgpt-quickstart
- Azure OpenAI role-based access control:
  https://learn.microsoft.com/azure/ai-services/openai/how-to/role-based-access-control

## Pouziti

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

## Asynchronni worker (Contributte RabbitMQ)

Knihovna obsahuje worker service `InvoiceParseWorker::process(array $message)`.

Priklad payloadu zpravy:

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

Ukazkova integrace je v [examples/rabbitmq.neon](examples/rabbitmq.neon) a [examples/InvoiceConsumer.php](examples/InvoiceConsumer.php).

## Poznamky

- Pro oskenovane PDF se OCR resi na strane Azure Document Intelligence.
- Regex fallback slouzi jako doplnek, kdyz DI/LLM vrati neuplna data.
- Validator hlida zakladni konzistenci castek a dat.
