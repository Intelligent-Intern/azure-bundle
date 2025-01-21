<?php

namespace App\Service\Embedding;

use App\Contract\EmbeddingServiceInterface;
use App\Contract\LogServiceInterface;
use App\Contract\RateLimiterInterface;
use App\DTO\EmbeddingResult;
use App\Service\VaultService;
use OpenAI;
use OpenAI\Client as OpenAIClient;
use Throwable;

class AzureEmbeddingService implements EmbeddingServiceInterface
{
    private string $apiKey;
    private string $endpoint;
    private array $models;
    private LogServiceInterface $logger;

    public function __construct(
        private readonly VaultService $vaultService,
        private readonly LogServiceFactory $logServiceFactory,
        private readonly RateLimiterInterface $rateLimiter
    ) {
        $this->logger = $this->logServiceFactory->create();
        $config = $this->vaultService->fetchSecret('secret/data/data/azure');
        $this->apiKey = $config['api_key'] ?? throw new \RuntimeException('API Key for Azure is not set in Vault.');
        $this->endpoint = $config['api_endpoint'] ?? throw new \RuntimeException('API Endpoint for Azure is not set in Vault.');
        $this->models = $config['models'] ?? throw new \RuntimeException('Model configurations are not set in Vault.');
    }

    public function supports(string $provider): bool
    {
        return strtolower($provider) === 'azure';
    }

    /**
     * @throws Throwable
     */
    public function generateEmbedding(string $input, string $model): EmbeddingResult
    {
        $modelConfig = $this->getModelConfig($model);
        $client = OpenAI::client($this->apiKey);
        $payload = [
            'model' => $modelConfig['deploymentId'],
            'input' => $input,
        ];
        $this->logger->info('Sending request to Azure Embeddings API.', ['payload' => $payload]);

        try {
            $response = $client->embeddings()->create($payload);
            if (empty($response->data[0]->embedding)) {
                throw new \RuntimeException('No embedding returned from Azure Embeddings API.');
            }

            $tokensUsed = [
                'requestTokens' => $response->usage->prompt_tokens ?? 0,
                'responseTokens' => $response->usage->completion_tokens ?? 0,
                'totalTokens' => $response->usage->total_tokens ?? 0,
            ];
            $this->logger->info('Received response from Azure Embeddings API.', [
                'response' => $response,
                'tokensUsed' => $tokensUsed,
            ]);

            return new EmbeddingResult(
                $response->data[0]->embedding,
                $modelConfig['deploymentId'],
                $response->id ?? 'unknown'
            );
        } catch (Throwable $e) {
            $this->logger->error('Error during Azure Embedding request.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function getModelConfig(string $model): array
    {
        if (!isset($this->models[$model])) {
            throw new \RuntimeException("Model configuration for '$model' not found.");
        }
        return $this->models[$model];
    }
}
