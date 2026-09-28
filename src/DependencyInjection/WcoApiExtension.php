<?php

declare(strict_types=1);

namespace WcoApi\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class WcoApiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wco_api.base_url', $config['base_url']);
        $container->setParameter('wco_api.vpbx', $config['vpbx']);
        $container->setParameter('wco_api.username', $config['username']);
        $container->setParameter('wco_api.password', $config['password']);
        $container->setParameter('wco_api.recording_password', $config['recording_password']);
        $container->setParameter('wco_api.ssl_verify', $config['ssl_verify']);
        $container->setParameter('wco_api.profile', $config['profile']);
        $container->setParameter('wco_api.profiles_file', $config['profiles_file']);
        $container->setParameter('wco_api.storage.driver', $config['storage']['driver']);
        $container->setParameter('wco_api.storage.path', $config['storage']['path']);
        $container->setParameter('wco_api.db_track_downloads', $config['db_track_downloads']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Domyślne env() żeby %env(...)% w Configuration nie wywaliło kontenera
        $container->setParameter('env(WCO_BASE_URL)', 'https://wco.orange.pl/admin_api');
        $container->setParameter('env(WCO_VPBX)', '');
        $container->setParameter('env(WCO_USER)', '');
        $container->setParameter('env(WCO_PASS)', '');
        $container->setParameter('env(WCO_REC_PASS)', '');
        $container->setParameter('env(WCO_SSL_VERIFY)', '0');
        $container->setParameter('env(WCO_PROFILE)', '');
        $container->setParameter('env(WCO_PROFILES_FILE)', '');
        $container->setParameter('env(WCO_STORAGE_DRIVER)', 'local');
        $container->setParameter('env(WCO_STORAGE_PATH)', 'var/recordings');
        $container->setParameter('env(WCO_DB_TRACK_DOWNLOADS)', '1');

        // Doctrine ORM 3: mapping przez prepend (bez addEntityNamespace z compiler passa)
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'WcoApi' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/Entity',
                            'prefix' => 'WcoApi\\Entity',
                            'alias' => 'WcoApi',
                        ],
                    ],
                ],
            ]);
        }
    }

    public function getAlias(): string
    {
        return 'wco_api';
    }
}
