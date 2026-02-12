<?php

namespace PfaffKIT\Essa\Adapters\StorageMongo;

use MongoDB\Collection as MongoCollection;
use MongoDB\Model\BSONArray as MongoBSONArray;
use MongoDB\Model\BSONDocument as MongoBSONDocument;
use PfaffKIT\Essa\EventSourcing\Projection\Projection;
use PfaffKIT\Essa\EventSourcing\Projection\ProjectionRepository;
use PfaffKIT\Essa\EventSourcing\Serializer\ProjectionSerializer;
use PfaffKIT\Essa\Shared\Identity;

/**
 * @template T of Projection
 */
abstract class AbstractProjectionRepository implements ProjectionRepository
{
    protected MongoCollection $collection;

    public function __construct(
        private readonly MongoDatabase $database,
        private readonly ProjectionSerializer $projectionSerializer,
    ) {
        $this->collection = $database->selectCollection(static::getProjectionClass()::getProjectionName());
    }

    /**
     * @param T $projection
     */
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

    /**
     * @returns T|null
     */
    public function getById(Identity $id): ?Projection
    {
        $data = $this->collection->findOne(['_id' => (string) $id]);

        return $data ? $this->denormalizeDocument($data) : null;
    }

    /**
     * @returns T[]
     */
    public function findBy(array $criteria): array
    {
        return array_map(
            fn ($data) => $this->denormalizeDocument($data),
            $this->collection->find($this->normalizeCriteria($criteria))->toArray()
        );
    }

    /**
     * @returns T|null
     */
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

    /**
     * @returns T
     */
    private function denormalizeDocument($data): Projection
    {
        if (!($data instanceof \stdClass) && !($data instanceof MongoBSONDocument)) {
            throw new \InvalidArgumentException('Expected stdClass or BSONDocument, got '.(is_object($data) ? get_class($data) : gettype($data)));
        }

        $dataArray = $this->bsonToArray($data);

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

    /**
     * Recursively convert BSONDocument/BSONArray to plain PHP arrays/stdClass.
     */
    private function bsonToArray($value)
    {
        if ($value instanceof MongoBSONDocument) {
            $value = (array) $value;
        }

        if ($value instanceof MongoBSONArray) {
            $value = $value->getArrayCopy();
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->bsonToArray($v);
            }
        }

        return $value;
    }

    /**
     * @returns class-string<T>
     */
    abstract public static function getProjectionClass(): string;
}
