<?php

namespace Atk4\Symfony\Module\DependencyInjection;

use Atk4\Symfony\Module\Atk4App;
use Atk4\Symfony\Module\Atk4Persistence;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class Atk4Extension extends Extension
{
    final public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $processor = new Processor();
        $configuration = $this->getConfiguration($configs, $containerBuilder);
        $config = $processor->processConfiguration($configuration, $configs);

        $yamlFileLoader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__.'/../../config'));
        $yamlFileLoader->load('services.yaml');

        $definition = $containerBuilder->getDefinition(Atk4App::class);
        $definition->setArgument('$config', $config);

        $definition = $containerBuilder->getDefinition(Atk4Persistence::class);
        $definition->setArgument('$config', $config);
    }
}
