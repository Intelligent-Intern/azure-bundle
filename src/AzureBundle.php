<?php

namespace IntelligentIntern\AzureBundle;

use IntelligentIntern\AzureBundle\DependencyInjection\Compiler\AIServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AzureBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AIServiceCompilerPass());
    }
}
