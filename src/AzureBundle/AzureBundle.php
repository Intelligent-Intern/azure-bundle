<?php

namespace src\AzureBundle;

use src\AzureBundle\DependencyInjection\Compiler\AIServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AzureBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AIServiceCompilerPass());
    }
}
