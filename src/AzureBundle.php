<?php

namespace IntelligentIntern\AzureBundle;

use App\DependencyInjection\Compiler\EmbeddingStrategyCompilerPass;
use App\DependencyInjection\Compiler\ChatCompletionStrategyCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AzureBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.yaml');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EmbeddingStrategyCompilerPass());
        $container->addCompilerPass(new ChatCompletionStrategyCompilerPass());
    }
}
