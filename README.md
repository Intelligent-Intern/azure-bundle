# Intelligent Intern Azure Bundle

The `intelligent-intern/azure-bundle` integrates Azure OpenAI with the [Intelligent Intern Core Framework](https://github.com/Intelligent-Intern/core), allowing seamless AI functionality for embedding generation.

## Installation

Install the bundle using Composer:

~~~bash
composer require intelligent-intern/azure-bundle
~~~

## Configuration

Ensure the following secret is set in vault:

~~~env
secret/data/data/azure:
  api_key: your_azure_api_key
  api_endpoint: your_azure_endpoint
  deployment_id: your_azure_deployment_id
  api_version: the_azure_version
~~~

To use the bundle, ensure the `AI_PROVIDER` is set to `"azure"`.

## Usage

Once the bundle is installed and configured, the Core framework will dynamically detect the Azure OpenAI service via the `ai.strategy` tag.

The service will be available via the `AIServiceFactory`:

~~~php
<?php

namespace App\Controller;

use App\Service\Api\AIServiceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmbeddingController extends AbstractController
{
    public function __construct(
        private AIServiceFactory $aiServiceFactory
    ) {}

    public function generateEmbedding(Request $request): JsonResponse
    {
        $input = $request->get('input', '');

        if (empty($input)) {
            return new JsonResponse(['error' => 'Input cannot be empty'], 400);
        }

        try {
            $aiService = $this->aiServiceFactory->create();
            $embedding = $aiService->generateEmbedding($input);

            return new JsonResponse(['embedding' => $embedding]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
~~~

## Extensibility

This bundle is specifically designed to integrate with `intelligent-intern/core`. It leverages the dynamic service discovery mechanism to ensure seamless compatibility.

If you'd like to add additional strategies, simply create a similar bundle that implements the `AIServiceInterface` and tag its service with `ai.strategy`.

For example:

~~~yaml
services:
  Your\CustomBundle\Service\CustomAIService:
    tags: ['ai.strategy']
~~~

## License

This bundle is open-sourced software licensed under the [MIT license](LICENSE).
