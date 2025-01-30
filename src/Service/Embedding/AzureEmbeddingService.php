<?php

namespace IntelligentIntern\AzureBundle\Service\Embedding;

use App\Contract\EmbeddingServiceInterface;
use App\Contract\LogServiceInterface;
use App\DTO\EmbeddingResult;
use App\Factory\LogServiceFactory;
use App\Service\VaultService;
use OpenAI;
use OpenAI\Client as OpenAIClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

class AzureEmbeddingService implements EmbeddingServiceInterface
{
    private array $models;
    private OpenAIClient $openAiClient;
    private LogServiceInterface $logger;

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function __construct(
        private readonly VaultService $vaultService,
        private readonly LogServiceFactory $logServiceFactory
    ) {
        $this->logger = $this->logServiceFactory->create();
        $config = $this->vaultService->fetchSecret('secret/data/data/azure');
        $apiKey = $config['api_key'] ?? throw new \RuntimeException('Azure API Key not set.');
        $endpoint = $config['api_endpoint'] ?? throw new \RuntimeException('Azure API Endpoint not set.');
        $apiVersion = $config['api_version'] ?? '2023-05-15'; // Default falls nicht gesetzt
        $this->models = $config['models'] ?? throw new \RuntimeException('Model configurations missing.');
        $this->logger->info('Initializing Azure OpenAI Client.');
        $this->openAiClient = OpenAI::factory()
            ->withBaseUri("{$endpoint}/openai/deployments/")
            ->withApiKey($apiKey)
            ->withQueryParams(['api-version' => $apiVersion])
            ->make();
    }

    public function supports(string $provider): bool
    {
        return strtolower($provider) === 'azure';
    }

    public function generateEmbedding(string $input, string $model): EmbeddingResult
    {
        $this->logger->info("Generating embedding for model: $model");
        $modelConfig = $this->models[$model] ?? throw new \RuntimeException("Model config for '$model' not found.");
        try {
            $this->logger->debug('Sending request to Azure OpenAI.', ['input' => substr($input, 0, 100) . '...']);
            $response = $this->openAiClient->embeddings()->create([
                'model' => $modelConfig['deploymentId'],
                'input' => $input,
            ]);
            if (empty($response->data[0]->embedding)) {
                throw new \RuntimeException('No embedding returned.');
            }
            $this->logger->info('Received embedding response from Azure OpenAI.');
            return new EmbeddingResult(
                $response->data[0]->embedding,
                $modelConfig['deploymentId'],
                $response->id ?? 'unknown'
            );
        } catch (Throwable $e) {
            $this->logger->error('Azure OpenAI API request failed.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Azure OpenAI API request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
