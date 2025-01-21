<?php

namespace IntelligentIntern\AzureBundle\Service\ChatCompletion;

use App\Factory\LogServiceFactory;
use App\Service\VaultService;
use App\Contract\ChatHistoryInterface;
use App\Contract\ChatCompletionServiceInterface;
use App\Contract\LogServiceInterface;
use App\Contract\RateLimiterInterface;
use App\DTO\ChatCompletionResult;
use App\Contract\AskPermissionInterface;
use OpenAI;
use OpenAI\Client as OpenAIClient;
use Throwable;

class AzureChatCompletionService implements ChatCompletionServiceInterface
{
    private string $apiKey;
    private string $endpoint;
    private array $models;
    private LogServiceInterface $logger;

    public function __construct(
        private readonly LogServiceFactory $logServiceFactory,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly VaultService $vaultService,
        private readonly AskPermissionInterface $askPermission
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
    public function generateResponse(string $model, ChatHistoryInterface $chatHistory, array $options = []): ChatCompletionResult
    {
        $modelConfig = $this->getModelConfig($model);
        $this->askPermission
            ->addProvider('azure')
            ->addChatHistory($chatHistory)
            ->addModelConfig($modelConfig)
            ->addModelName($model);
        $permissionRequest = $this->rateLimiter->acquirePermit($this->askPermission);
        if (!$permissionRequest->isGranted()) {
            throw new \RuntimeException('Permission to perform this request was denied.');
        }
        $this->logger->info('Rate limiter granted permission.', [
            'metadata' => $permissionRequest->getMetadata(),
        ]);
        $payload = array_merge([
            'model' => $modelConfig['deploymentId'],
            'messages' => $chatHistory->getMessages(),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'top_p' => $options['top_p'] ?? 1.0,
            'n' => $options['n'] ?? 1,
        ], $options);
        $tokensExpected = $permissionRequest->getMetadata()['tokensExpected'] ?? 'unknown';
        $this->logger->info('Sending chat request to Azure.', [
            'payload' => $payload,
            'tokensExpected' => $tokensExpected,
        ]);
        try {
            $client = OpenAI::client($this->apiKey);
            $response = $client->chat()->create($payload);
            if (empty($response->choices)) {
                throw new \RuntimeException('No choices returned from Azure Chat Completion.');
            }
            $tokensUsed = [
                'requestTokens' => $response->usage->prompt_tokens ?? 0,
                'responseTokens' => $response->usage->completion_tokens ?? 0,
                'totalTokens' => $response->usage->total_tokens ?? 0,
            ];
            $this->logger->info('Received response from Azure.', [
                'response' => $response,
                'tokensUsed' => $tokensUsed,
            ]);

            return new ChatCompletionResult(
                content: $response->choices[0]->message->content ?? throw new \RuntimeException('No content in response.'),
                modelUsed: $modelConfig['deploymentId'],
                requestId: $response->id ?? 'unknown',
                rawChoices: $response->choices
            );
        } catch (Throwable $e) {
            $this->logger->error('Error during Azure chat completion request.', [
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
