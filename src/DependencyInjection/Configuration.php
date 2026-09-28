<?php

declare(strict_types=1);

namespace WcoApi\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('wco_api');

        $tree->getRootNode()
            ->children()
                ->scalarNode('base_url')->defaultValue('%env(WCO_BASE_URL)%')->end()
                ->scalarNode('vpbx')->defaultValue('%env(WCO_VPBX)%')->end()
                ->scalarNode('username')->defaultValue('%env(WCO_USER)%')->end()
                ->scalarNode('password')->defaultValue('%env(WCO_PASS)%')->end()
                ->scalarNode('recording_password')->defaultValue('%env(WCO_REC_PASS)%')->end()
                ->scalarNode('ssl_verify')->defaultValue('%env(WCO_SSL_VERIFY)%')->end()
                ->scalarNode('profile')->defaultValue('%env(WCO_PROFILE)%')->end()
                ->scalarNode('profiles_file')->defaultValue('%env(WCO_PROFILES_FILE)%')->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('driver')->defaultValue('%env(WCO_STORAGE_DRIVER)%')->end()
                        ->scalarNode('path')->defaultValue('%env(WCO_STORAGE_PATH)%')->end()
                    ->end()
                ->end()
                ->scalarNode('db_track_downloads')->defaultValue('%env(bool:WCO_DB_TRACK_DOWNLOADS)%')->end()
            ->end();

        return $tree;
    }
}
