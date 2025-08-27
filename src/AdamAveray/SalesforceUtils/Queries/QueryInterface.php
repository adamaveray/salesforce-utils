<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Queries;

use Phpforce\SoapClient\Result\QueryResult;
use Phpforce\SoapClient\Result\RecordIterator;
use Phpforce\SoapClient\Result\SObject;

interface QueryInterface
{
    /**
     * @param array|null $args Arguments to bind before executing
     * @return RecordIterator
     */
    public function query(?array $args = null): RecordIterator;

    /**
     * @param array|null $args Arguments to bind before executing
     * @return QueryResult[]
     */
    public function queryAll(?array $args = null): array;

    /**
     * @param array|null $args Arguments to bind before executing
     * @return SObject|null The first result from the query, or null if no results
     */
    public function queryOne(?array $args = null): ?SObject;
}
