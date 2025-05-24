<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo;

use MongoDB\Client as MongoClient;
use MongoDB\Database;

class MongoDatabase extends Database
{
    public function __construct(
        string $connectionString,
        string $databaseName,
    ) {
        $client = new MongoClient($connectionString);
        parent::__construct($client->getManager(), $databaseName);
    }
}
