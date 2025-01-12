<?php

namespace src\AzureBundle\Service;

use App\Service\Api\Strategies\AIServiceInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AzureService implements AIServiceInterface
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $endpoint;
    private string $deploymentId;
    private string $apiVersion;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['AZURE_API_KEY'] ?? throw new \RuntimeException('AZURE_API_KEY is not set');
        $this->endpoint = $_ENV['AZURE_API_ENDPOINT'] ?? throw new \RuntimeException('AZURE_API_ENDPOINT is not set');
        $this->deploymentId = $_ENV['AZURE_DEPLOYMENT_ID'] ?? throw new \RuntimeException('AZURE_DEPLOYMENT_ID is not set');
        $this->apiVersion = $_ENV['AZURE_API_VERSION'] ?? throw new \RuntimeException('AZURE_API_VERSION is not set');
    }

    public function generateEmbedding(string $input): array
    {
        $response = $this->httpClient->request('POST', "{$this->endpoint}/openai/deployments/{$this->deploymentId}/embeddings?api-version={$this->apiVersion}", [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => ['model' => 'text-embedding-3-small', 'input' => $input],
        ]);

        $data = $response->toArray();
        return $data['data'][0]['embedding'] ?? throw new \RuntimeException('Failed to fetch embedding from Azure');
    }
}
