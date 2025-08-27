<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Client;

use AdamAveray\SalesforceUtils\Queries\QueryInterface;
use Phpforce\SoapClient\Result;
use Phpforce\SoapClient\Result\SObject;

interface ClientInterface extends \Phpforce\SoapClient\ClientInterface
{
    /**
     * @param string $query
     * @return Result\RecordIterator
     */
    public function rawQuery(string $query): Result\RecordIterator;

    /**
     * @param string $objectName
     * @return Result\DescribeSObjectResult
     */
    public function describeSObject(
        string $objectName
    ): ?Result\DescribeSObjectResult;

    /**
     * Builds a QueryInterface object suitable for calling multiple times with different arguments
     *
     * @param string $query The parameterised SOQL query
     * @param array|null $globalArgs Arguments to bind to every execution of the query
     * @return QueryInterface
     */
    public function prepare(
      string $query,
      ?array $globalArgs = null
    ): QueryInterface;

    /**
     * @param string|QueryInterface $query The parameterised SOQL query, or a pre-prepared QueryInterface object
     * @param array|null $args Arguments to bind to the query
     * @return SObject|null
     */
    public function queryOne($query, ?array $args = null): ?SObject;

    /**
     * @param SObject $object The object to update
     * @param string $type    The type of object being updated
     * @return Result\SaveResult
     */
    public function updateOne(SObject $object, string $type): Result\SaveResult;

    /**
     * @param object $object The object to create
     * @param string $type   The type of object to create
     * @return Result\SaveResult
     */
    public function createOne($object, string $type): Result\SaveResult;

    /**
     * @param string|SObject $id The ID of the object to delete or the object itself
     * @return Result\DeleteResult
     */
    public function deleteOne($id): Result\DeleteResult;

    /**
     * @param array $fields      The fields to retrieve
     * @param string $id         The ID of the object to retrieve
     * @param string $objectType The type of object to fetch
     * @return SObject|null      The object, or `null` if not found
     */
    public function retrieveOne(
        array $fields,
        string $id,
        string $objectType
    ): ?SObject;

    /**
     * @param string $id      The ID of the object to undelete
     * @return Result\UndeleteResult
     */
    public function undeleteOne(string $id): Result\UndeleteResult;

    /**
     * @param $externalIdFieldName
     * @param SObject $object
     * @param $type
     * @return Result\UpsertResult
     */
    public function upsertOne(
        $externalIdFieldName,
        SObject $object,
        $type
    ): Result\UpsertResult;
}
