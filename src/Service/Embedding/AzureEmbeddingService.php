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

    private string $endpoint;
    private string $apiVersion;
    private string $apiKey;

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
        $this->apiKey = $config['api_key'] ?? throw new \RuntimeException('Azure API Key not set.');
        $this->endpoint = $config['endpoint'] ?? throw new \RuntimeException('Azure API Endpoint not set.');
        $this->apiVersion = $config['api_version'] ?? '2023-05-15';
        $models = $config['models'] ?? throw new \RuntimeException('Model configurations missing.');
        $models_std_class = json_decode($models);
        $this->models = json_decode(json_encode($models_std_class), true);
        $this->logger->info('Initializing Azure OpenAI Client.');
    }

    public function supports(string $provider): bool
    {
        return strtolower($provider) === 'azure';
    }

    public function generateEmbedding(string $input, string $model): EmbeddingResult
    {
        $this->logger->info("Generating embedding for model: $model");
        try {
            $modelConfig = $this->models[$model] ?? throw new \RuntimeException("Model config for '$model' not found.");

            $client = OpenAI::factory()
                ->withBaseUri($this->endpoint.'/openai/deployments/'.$modelConfig['deploymentId'])
                ->withHttpHeader('api-key', $this->apiKey)
                ->withQueryParam('api-version', $this->apiVersion)
                ->make();

            $response = $client->embeddings()->create([
                'model' => $modelConfig['deploymentId'],
                'input' => $input,
            ]);

            $data = $response->toArray();
            if (empty($data['data'][0]['embedding'])) {
                throw new \RuntimeException('No embedding returned.');
            }
            $embedding = $data['data'][0]['embedding'];
            $this->logger->info('Received embedding response from Azure OpenAI.');
            return new EmbeddingResult(
                $embedding,
                $modelConfig['deploymentId']
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
