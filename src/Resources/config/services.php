<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WcoApi\Command\DbInstallCommand;
use WcoApi\Persistence\DownloadTrackerInterface;
use WcoApi\Repository\RecordingDownloadRepository;
use WcoApi\Storage\RecordingStorageInterface;
use WcoApi\Storage\WcoStorageFactory;
use WcoApi\WcoConfig;
use WcoApi\WcoProfiles;
use WcoApi\WcoProfilesFactory;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->load('WcoApi\\Command\\', __DIR__ . '/../../Command/')
        ->exclude([
            __DIR__ . '/../../Command/AbstractWcoCommand.php',
            __DIR__ . '/../../Command/DbInstallCommand.php',
        ]);

    $services->set(DbInstallCommand::class)
        ->arg('$em', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->arg('$projectDir', param('kernel.project_dir'));

    $services->set(WcoConfig::class)
        ->args([
            param('wco_api.vpbx'),
            param('wco_api.username'),
            param('wco_api.password'),
            param('wco_api.recording_password'),
            param('wco_api.base_url'),
            param('wco_api.storage.path'),
            param('wco_api.ssl_verify'),
        ]);

    $services->set(WcoProfiles::class)
        ->factory([WcoProfilesFactory::class, 'create'])
        ->args([
            param('kernel.project_dir'),
            param('wco_api.profiles_file'),
            param('wco_api.profile'),
        ]);

    $services->set(RecordingDownloadRepository::class)
        ->autowire()
        ->autoconfigure();

    $services->set(WcoStorageFactory::class)
        ->args([
            '$projectDir' => param('kernel.project_dir'),
            '$driver' => param('wco_api.storage.driver'),
            '$path' => param('wco_api.storage.path'),
            '$dbTrack' => param('wco_api.db_track_downloads'),
            '$downloadRepository' => service(RecordingDownloadRepository::class)->nullOnInvalid(),
            '$em' => service('doctrine.orm.entity_manager')->nullOnInvalid(),
        ]);

    $services->set(RecordingStorageInterface::class)
        ->factory([service(WcoStorageFactory::class), 'createStorage']);

    $services->set(DownloadTrackerInterface::class)
        ->factory([service(WcoStorageFactory::class), 'createTracker'])
        ->args([service(RecordingStorageInterface::class)]);
};
