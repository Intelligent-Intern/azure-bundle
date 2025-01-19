<?php

namespace IntelligentIntern\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AIServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('App\Factory.AIServiceFactory')) {
            return;
        }

        $definition = $container->findDefinition('App\Factory.AIServiceFactory');

        $taggedServices = $container->findTaggedServiceIds('ai.strategy');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addStrategy', [new Reference($id)]);
        }
    }
}
