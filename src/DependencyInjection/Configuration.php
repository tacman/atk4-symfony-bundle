<?php

namespace Atk4\Symfony\Module\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('atk4');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('version')->defaultValue('5.0-dev')->end()
            ->scalarNode('title')->defaultValue('Symfony ATK4 Bundle')->end()
            ->arrayNode('cdn')
                ->children()
                    ->scalarNode('atk')->defaultValue('/bundles/atk4')->end()
                    ->scalarNode('jquery')->defaultValue('/bundles/atk4/external/jquery/dist')->end()
                    ->scalarNode('fomantic')->defaultValue('/bundles/atk4/external/fomantic-ui/dist')->end()
                    ->scalarNode('flatpickr')->defaultValue('/bundles/atk4/external/flatpickr/dist')->end()
                    ->scalarNode('highlight')->defaultValue('/bundles/atk4/external/@highlightjs/cdn-assets')->end()
                    ->scalarNode('chart')->defaultValue('/bundles/atk4/external/chart.js/dist')->end()
                ->end()
            ->end()
                ->arrayNode('security')
                ->children()
                    ->scalarNode('user_class')->defaultValue('App\Models\User')->end()
                ->end()
            ->end()
                ->arrayNode('filesystem')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('path')->defaultValue('var/storage/local')->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('persistences')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('driver')->defaultValue('mysql')->end()
                        ->scalarNode('host')->defaultValue('127.0.0.1')->end()
                        ->scalarNode('port')->defaultValue(3306)->end()
                        ->scalarNode('name')->defaultValue('test')->end()
                        ->scalarNode('user')->defaultValue('root')->end()
                        ->scalarNode('pass')->defaultValue('root')->end()
                        ->scalarNode('charset')->defaultValue('utf8')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
