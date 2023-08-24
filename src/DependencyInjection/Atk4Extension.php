<?php

namespace Atk4\Symfony\Module\DependencyInjection;

use Atk4\Symfony\Module\Atk4App;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Atk4Extension extends Extension
{
    /**
     * @inheritDoc
     */
    final public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = $this->getConfiguration($configs, $container);
        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $definition = $container->getDefinition(Atk4App::class);
        $definition->setArgument('$config', $config);

    }
}