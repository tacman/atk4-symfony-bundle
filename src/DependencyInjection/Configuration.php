<?php

namespace Atk4\Symfony\Module\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('atk4');

        $builder->getRootNode()
            ->children()
            ->scalarNode('version')->defaultValue('5.0-dev')->end()
            ->scalarNode('title')->defaultValue('Symfony ATK4 Bundle')->end()
            ->arrayNode('cdn')
                ->children()
                    ->scalarNode('atk')         ->defaultValue('/bundles/atk4')->end()
                    ->scalarNode("jquery")      ->defaultValue('/bundles/atk4/external/jquery/dist')->end()
                    ->scalarNode("fomantic") ->defaultValue('/bundles/atk4/external/fomantic-ui/dist')->end()
                    ->scalarNode("flatpickr")   ->defaultValue('/bundles/atk4/external/flatpickr/dist')->end()
                    ->scalarNode("highlight")->defaultValue('/bundles/atk4/external/@highlightjs/cdn-assets')->end()
                    ->scalarNode("chart")    ->defaultValue('/bundles/atk4/external/chart.js/dist')->end()
                ->end()
            ->end()
            ->scalarNode('urlBuildingExt')->defaultValue('')->end()
            ->booleanNode('catchExceptions')->defaultValue(true)->end()
            ->booleanNode('catchRunawayCallbacks')->defaultValue(true)->end()
            ->booleanNode('alwaysRun')->defaultValue(false)->end()
            ->booleanNode('callExit')->defaultValue(false)->end()
            ->end();

        return $builder;
    }
}