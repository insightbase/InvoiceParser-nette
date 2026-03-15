<?php

declare(strict_types=1);

namespace InsightBase\InvoiceParserNette\DI;

use GuzzleHttp\Client;
use InsightBase\InvoiceParserNette\Extractor\CzechRegexExtractor;
use InsightBase\InvoiceParserNette\Parser\AzureDocumentIntelligenceAnalyzer;
use InsightBase\InvoiceParserNette\Parser\AzureOpenAiLlmNormalizer;
use InsightBase\InvoiceParserNette\Parser\InvoiceMapper;
use InsightBase\InvoiceParserNette\Parser\InvoiceParser;
use InsightBase\InvoiceParserNette\Queue\InvoiceParseWorker;
use InsightBase\InvoiceParserNette\Validation\InvoiceValidator;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use RuntimeException;

final class InvoiceParserExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'azureDi' => Expect::structure([
                'endpoint' => Expect::string()->nullable(),
                'apiKey' => Expect::string()->nullable(),
                'model' => Expect::string('prebuilt-invoice'),
                'apiVersion' => Expect::string('2023-07-31'),
                'maxPollAttempts' => Expect::int(25),
                'pollIntervalMs' => Expect::int(1000),
            ]),
            'llm' => Expect::structure([
                'enabled' => Expect::bool(false),
                'endpoint' => Expect::string()->nullable(),
                'deployment' => Expect::string()->nullable(),
                'apiKey' => Expect::string()->nullable(),
                'apiVersion' => Expect::string('2024-10-21'),
            ]),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var object $config */
        $config = $this->getConfig();

        if ($config->azureDi->endpoint === null || $config->azureDi->apiKey === null) {
            throw new RuntimeException('invoiceParser.azureDi.endpoint and invoiceParser.azureDi.apiKey are required.');
        }

        $httpClient = $builder->addDefinition($this->prefix('httpClient'));
        $httpClient->setFactory(Client::class);

        $builder->addDefinition($this->prefix('documentAnalyzer'))
            ->setFactory(AzureDocumentIntelligenceAnalyzer::class, [
                '@' . $this->prefix('httpClient'),
                $config->azureDi->endpoint,
                $config->azureDi->apiKey,
                $config->azureDi->model,
                $config->azureDi->apiVersion,
                $config->azureDi->maxPollAttempts,
                $config->azureDi->pollIntervalMs,
            ]);

        if ($config->llm->enabled) {
            if ($config->llm->endpoint === null || $config->llm->deployment === null || $config->llm->apiKey === null) {
                throw new RuntimeException('invoiceParser.llm endpoint, deployment and apiKey are required when llm.enabled=true.');
            }

            $builder->addDefinition($this->prefix('llmNormalizer'))
                ->setFactory(AzureOpenAiLlmNormalizer::class, [
                    '@' . $this->prefix('httpClient'),
                    $config->llm->endpoint,
                    $config->llm->deployment,
                    $config->llm->apiKey,
                    $config->llm->apiVersion,
                ]);
        }

        $builder->addDefinition($this->prefix('regexExtractor'))->setFactory(CzechRegexExtractor::class);
        $builder->addDefinition($this->prefix('invoiceMapper'))->setFactory(InvoiceMapper::class);
        $builder->addDefinition($this->prefix('validator'))->setFactory(InvoiceValidator::class);

        $builder->addDefinition($this->prefix('invoiceParser'))
            ->setFactory(InvoiceParser::class, [
                '@' . $this->prefix('documentAnalyzer'),
                '@' . $this->prefix('regexExtractor'),
                '@' . $this->prefix('invoiceMapper'),
                '@' . $this->prefix('validator'),
                $config->llm->enabled ? '@' . $this->prefix('llmNormalizer') : null,
            ]);

        $builder->addDefinition($this->prefix('worker'))
            ->setFactory(InvoiceParseWorker::class, ['@' . $this->prefix('invoiceParser')]);
    }
}
