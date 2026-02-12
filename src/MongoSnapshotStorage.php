<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo;

use PfaffKIT\Essa\EventSourcing\Snapshot;
use PfaffKIT\Essa\EventSourcing\SnapshotStorage;
use PfaffKIT\Essa\Shared\EventTimestamp;
use PfaffKIT\Essa\Shared\Id;
use PfaffKIT\Essa\Shared\Identity;

readonly class MongoSnapshotStorage implements SnapshotStorage
{
    public function __construct(
        private MongoDatabase $database,
    ) {}

    public function load(string $snapshotName, int $snapshotVersion, Identity $aggregateId): ?Snapshot
    {
        $collection = $this->database->selectCollection('snapshot_'.$snapshotName);

        $data = $collection->findOne(['_id' => (string) $aggregateId]);

        if (!$data) {
            return null;
        }

        if ($data['_version'] !== $snapshotVersion) {
            return null;
        }

        return new Snapshot(
            $aggregateId,
            $snapshotName,
            $snapshotVersion,

            new EventTimestamp($data['_lastEventTimestamp']),
            Id::fromString($data['_lastEventId']),
            $this->prepareDataFromMongo((array) $data['_data'])
        );
    }

    public function save(Snapshot $snapshot): void
    {
        $collection = $this->database->selectCollection('snapshot_'.$snapshot->name);

        $collection->updateOne(
            ['_id' => (string) $snapshot->aggregateId],
            ['$set' => [
                '_version' => $snapshot->version,
                '_lastEventTimestamp' => $snapshot->lastEventTimestamp->epoch,
                '_lastEventId' => (string) $snapshot->lastEventId,
                '_data' => $this->prepareDataForMongo($snapshot->data),
            ]],
            ['upsert' => true]
        );
    }

    private function prepareDataForMongo(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if ($value instanceof \DateTimeInterface) {
                $value = new \MongoDB\BSON\UTCDateTime($value->getTimestamp() * 1000);
            }
        });

        return $data;
    }

    private function prepareDataFromMongo(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if ($value instanceof \MongoDB\BSON\UTCDateTime) {
                $value = $value->toDateTimeImmutable();
            }
            if ($value instanceof \MongoDB\Model\BSONArray) {
                $value = (array) $value;
            }
        });

        return $data;
    }
}
