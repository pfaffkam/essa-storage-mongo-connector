<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo;

use MongoDB\Collection as MongoCollection;
use PfaffKIT\Essa\EventSourcing\Projection\Projection;
use PfaffKIT\Essa\EventSourcing\Projection\ProjectionRepository;
use PfaffKIT\Essa\EventSourcing\Serializer\ProjectionSerializer;
use PfaffKIT\Essa\Shared\Identity;

abstract class AbstractProjectionRepository implements ProjectionRepository
{
    private MongoCollection $collection;

    public function __construct(
        private readonly MongoDatabase $database,
        private readonly ProjectionSerializer $projectionSerializer,
    ) {
        $this->collection = $database->selectCollection(static::getProjectionClass());
    }

    public function save(Projection $projection): void
    {
        $expectedType = static::getProjectionClass();
        if (!$projection instanceof $expectedType) {
            throw new \InvalidArgumentException(sprintf('Expected %s, got %s', static::getProjectionClass(), get_class($projection)));
        }

        $data = $this->projectionSerializer->normalize($projection);

        $this->collection->updateOne(
            ['_id' => (string) $projection->id],
            ['$set' => $data],
            ['upsert' => true]
        );
    }

    public function getById(Identity $id): ?Projection
    {
        $data = $this->collection->findOne(['_id' => (string) $id]);

        return $data ? $this->projectionSerializer->denormalize($data) : null;
    }

    public function findBy(array $criteria): array
    {
        return array_map(
            fn (array $data) => $this->denormalizeDocument($data),
            $this->collection->find($this->normalizeCriteria($criteria))->toArray()
        );
    }

    public function findOneBy(array $criteria): ?Projection
    {
        return $this->collection->findOne($this->normalizeCriteria($criteria))
            ? $this->denormalizeDocument($this->collection->findOne($this->normalizeCriteria($criteria)))
            : null;
    }

    public function deleteBy(array $criteria): int
    {
        return $this->collection->deleteMany($this->normalizeCriteria($criteria))->getDeletedCount();
    }

    private function denormalizeDocument($data): Projection
    {
        if (!($data instanceof \stdClass) && !($data instanceof \MongoDB\Model\BSONDocument)) {
            throw new \InvalidArgumentException('Expected stdClass or BSONDocument, got '.(is_object($data) ? get_class($data) : gettype($data)));
        }

        $dataArray = (array) $data;
        if (isset($data->_id)) {
            $dataArray['id'] = (string) $data->_id;
        }

        return $this->projectionSerializer->denormalize(
            $dataArray,
            static::getProjectionClass()
        );
    }

    private function normalizeCriteria(array $criteria): array
    {
        $normalized = [];

        foreach ($criteria as $key => $value) {
            if ($value instanceof Identity) {
                $normalized['_id'] = (string) $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
