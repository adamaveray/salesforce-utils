<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Client;

use AdamAveray\SalesforceUtils\Queries\Query;
use AdamAveray\SalesforceUtils\Queries\QueryInterface;
use AdamAveray\SalesforceUtils\Queries\SafeString;
use AdamAveray\SalesforceUtils\Writer;
use Phpforce\SoapClient\Result\DeleteResult;
use Phpforce\SoapClient\Result\DescribeSObjectResult;
use Phpforce\SoapClient\Result\RecordIterator;
use Phpforce\SoapClient\Result\SaveResult;
use Phpforce\SoapClient\Result\SObject;
use Phpforce\SoapClient\Result\UndeleteResult;
use Phpforce\SoapClient\Result\UpsertResult;

class Client extends \Phpforce\SoapClient\Client implements ClientInterface
{
    /** @var Writer $writer */
    private $writer;

    /** {@inheritdoc} */
    public function prepare(
        string $query,
        ?array $globalArgs = null
    ): QueryInterface {
        return new Query($this, $query, $globalArgs);
    }

    /** {@inheritdoc} */
    public function rawQuery(string $query): RecordIterator
    {
        return parent::query($query);
    }

    /**
     * @param string|Query $query The parameterised SOQL query, or a pre-prepared Query object
     * @param array|null $args Arguments to bind to the query
     * @return \Phpforce\SoapClient\Result\RecordIterator
     */
    public function query($query, ?array $args = null)
    {
        if (!$query instanceof QueryInterface) {
            $query = $this->prepare($query);
        }
        return $query->query($args);
    }

    /**
     * @param string|Query $query The parameterised SOQL query, or a pre-prepared Query object
     * @param array|null $args Arguments to bind to the query
     * @return \Phpforce\SoapClient\Result\QueryResult[]
     */
    public function queryAll($query, ?array $args = null)
    {
        if (!$query instanceof QueryInterface) {
            $query = $this->prepare($query);
        }
        return $query->queryAll($args);
    }

    /** {@inheritdoc} */
    public function queryOne($query, ?array $args = null): ?SObject
    {
        if (!$query instanceof QueryInterface) {
            $query = $this->prepare($query);
        }
        return $query->queryOne($args);
    }

    /**
     * @param mixed $value The value to escape
     * @param bool $isLike Whether the value is for a LIKE comparison
     * @param bool $quote Whether to quote the value
     * @return SafeString
     */
    public function escape($value, $isLike = false, $quote = true): SafeString
    {
        if (!$value instanceof SafeString) {
            $value = SafeString::escape($value, $isLike, $quote);
        }
        return $value;
    }

    /** {@inheritdoc} */
    public function describeSObject(string $objectName): ?DescribeSObjectResult
    {
        $result = $this->describeSObjects([$objectName]);
        return $result[0] ?? null;
    }

    /** {@inheritdoc} */
    public function updateOne(SObject $object, string $type): SaveResult
    {
        $result = $this->update([$object], $type);
        return $result[0];
    }

    /** {@inheritdoc} */
    public function createOne($object, string $type): SaveResult
    {
        if ($object instanceof SObject && $object->Id === null) {
            throw new \InvalidArgumentException(
                'SObjects without IDs cannot be used for creation - use simple \\stdClass objects',
            );
        }

        $result = $this->create([$object], $type);
        return $result[0];
    }

    /** {@inheritdoc} */
    public function deleteOne($id): DeleteResult
    {
        $result = $this->delete([$id]);
        return $result[0];
    }

    /**
     * @param array $ids      An array of object IDs or SObject instances
     * @return DeleteResult[]
     */
    public function delete(array $ids)
    {
        // Convert objects to IDs
        $processed = [];
        foreach ($ids as $id) {
            if ($id instanceof SObject) {
                $id = $id->getId();
            }
            $processed[] = $id;
        }

        return parent::delete($processed);
    }

    /** {@inheritdoc} */
    public function retrieveOne(
        array $fields,
        string $id,
        string $objectType
    ): ?SObject {
        $result = $this->retrieve($fields, [$id], $objectType);
        return $result[0] ?? null;
    }

    /** {@inheritdoc} */
    public function undeleteOne(string $id): UndeleteResult
    {
        $result = $this->undelete([$id]);
        return $result[0];
    }

    /** {@inheritdoc} */
    public function upsertOne(
        $externalIdFieldName,
        SObject $object,
        $type
    ): UpsertResult {
        $result = $this->upsert($externalIdFieldName, [$object], $type);
        $singleResult = $result[0];
        if ($singleResult instanceof \stdClass) {
            $singleResult = self::castGenericObject(
                $singleResult,
                UpsertResult::class,
            );
        }
        return $singleResult;
    }

    /**
     * @return Writer
     */
    public function getWriter(): Writer
    {
        if ($this->writer === null) {
            $this->writer = new Writer($this);
        }
        return $this->writer;
    }

    /** {@inheritDoc} */
    protected function createSoapVars(array $objects, $type): array
    {
        // Strip null ID values
        foreach ($objects as $i => $object) {
            if (!($object instanceof SObject) || $object->getId() !== null) {
                continue;
            }

            $objectValues = (array) $object;
            unset($objectValues['Id']);
            $objects[$i] = (object) $objectValues;
        }

        return parent::createSoapVars($objects, $type);
    }

    /**
     * @param \stdClass $genericObject The generic object to cast
     * @param string $className The name of the class to instantiate
     * @return object An instance of $className with properties from $genericObject assigned
     */
    private static function castGenericObject(
        \stdClass $genericObject,
        string $className
    ): object {
        $convertedObject = new $className();
        $reflectionClass = new \ReflectionClass($convertedObject);
        foreach ((array) $genericObject as $key => $value) {
            if ($reflectionClass->hasProperty($key)) {
                $property = $reflectionClass->getProperty($key);
                $property->setAccessible(true);
                $property->setValue($convertedObject, $value);
                $property->setAccessible(false);
            }
        }
        return $convertedObject;
    }
}
