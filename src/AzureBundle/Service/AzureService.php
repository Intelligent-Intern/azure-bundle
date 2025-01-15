<?php

namespace src\AzureBundle\Service;

use App\Interface\AIServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\VaultService;

class AzureService implements AIServiceInterface
{
    private string $apiKey;
    private string $endpoint;
    private string $deploymentId;
    private string $apiVersion;

    public function __construct(
        private HttpClientInterface $httpClient,
        private VaultService $vaultService,
        private LoggerInterface $logger
    ) {
        $config = $this->vaultService->fetchSecret('secret/data/data/azure');
        $this->apiKey = $config['api_key'] ?? throw new \RuntimeException('API Key for Azure is not set in Vault.');
        $this->endpoint = $config['api_endpoint'] ?? throw new \RuntimeException('API Endpoint for Azure is not set in Vault.');
        $this->deploymentId = $config['deployment_id'] ?? throw new \RuntimeException('Deployment ID for Azure is not set in Vault.');
        $this->apiVersion = $config['api_version'] ?? throw new \RuntimeException('API Version for Azure is not set in Vault.');
    }

    public function supports(string $provider): bool
    {
        return strtolower($provider) === 'azure';
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setVaultService(VaultService $vaultService): void
    {
        $this->vaultService = $vaultService;
    }

    public function generateEmbedding(string $input): array
    {
        $this->logger->info('Generating embedding using Azure OpenAI API.');

        $response = $this->httpClient->request('POST', "{$this->endpoint}/openai/deployments/{$this->deploymentId}/embeddings?api-version={$this->apiVersion}", [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'input' => $input,
                'model' => 'text-embedding-3-small',
            ],
        ]);

        $data = $response->toArray();

        return $data['data'][0]['embedding'] ?? throw new \RuntimeException('Failed to fetch embedding from Azure');
    }
}
