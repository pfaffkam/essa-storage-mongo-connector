<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo\Config;

use PfaffKIT\Essa\Internal\Configurator;
use PfaffKIT\Essa\Internal\ConfiguratorLogWriter;
use PfaffKIT\Essa\Internal\ExtensionConfigChanger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
final readonly class EnvironmentConfigurator implements Configurator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $rootDir,
    ) {}

    public static function getExtensionName(): string
    {
        return 'storage_mongo_connector';
    }

    public function shouldConfigure(): bool
    {
        return !$this->isMongoEnvironmentVariablesExists();
    }

    public function configure(ConfiguratorLogWriter $log, ExtensionConfigChanger $configChanger): void
    {
        // append .env file with environment variables
        $log->info('Creating .env file...');
        $envFile = $this->rootDir.DIRECTORY_SEPARATOR.'.env';
        if (!file_exists($envFile)) {
            touch($envFile);
        }

        // put environment block into .env file
        $envFileContent = file_get_contents($envFile);
        $envFileContent .= "\n###> pfaffkit/essa-storage-mongo-connector\nMONGODB_URL=mongodb://localhost:27017\nMONGODB_DBNAME=databasename\n###< pfaffkit/essa-storage-mongo-connector\n";

        file_put_contents($envFile, $envFileContent);

        // fix config if needed
        if ('init' == $configChanger->get('mongo_url')) {
            $configChanger->set('mongo_url', '%env(resolve:MONGODB_URL)%');
        }

        if ('init' == $configChanger->get('mongo_db_name')) {
            $configChanger->set('mongo_db_name', '%env(resolve:MONGODB_DBNAME)%');
        }

        $log->info('Please configure the environment variables in .env file.');
    }

    private function isMongoEnvironmentVariablesExists(): bool
    {
        return isset($_ENV['MONGODB_URL']) && isset($_ENV['MONGODB_DBNAME']);
    }
}
