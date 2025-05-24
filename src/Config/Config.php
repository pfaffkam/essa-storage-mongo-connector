<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo\Config;

use PfaffKIT\Essa\Adapters\StorageMongo\MongoDatabase;
use PfaffKIT\Essa\Internal\ExtensionConfig;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class Config extends ExtensionConfig
{
    public function __construct(
        public readonly string $mongoUrl,
        public readonly string $mongoDbName,
    ) {}

    public static function instantiate(array $config): ExtensionConfig
    {
        return new self(
            $config['mongo_url'],
            $config['mongo_db_name'],
        );
    }

    public static function getExtensionName(): string
    {
        return 'storage_mongo_connector';
    }

    public static function configure(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->scalarNode('mongo_url')->defaultValue('init')->end()
            ->scalarNode('mongo_db_name')->defaultValue('init')->end();
    }

    public static function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Register MongoDatabase service
        $services->set(MongoDatabase::class)
            ->public()
            ->args([
                '$connectionString' => $config['mongo_url'] ?? '',
                '$databaseName' => $config['mongo_db_name'] ?? '',
            ])
            ->autowire();

    }
}
