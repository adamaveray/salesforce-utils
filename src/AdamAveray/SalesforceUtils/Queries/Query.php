<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Queries;

use AdamAveray\SalesforceUtils\Client\ClientInterface;
use Phpforce\SoapClient\Result\RecordIterator;
use Phpforce\SoapClient\Result\SObject;

class Query implements QueryInterface
{
    /** @var ClientInterface $client */
    private $client;
    /** @var string $rawQuery The unprocessed SOQL query with parameter placeholders intact */
    private $rawQuery;
    /** @var array $parts */
    private $parts;
    /** @var array $globalArgs Arguments to bind to every execution of the query */
    private $globalArgs;

    /**
     * @param ClientInterface $client
     * @param string $query The parameterised SOQL query
     * @param array|null $globalArgs Global arguments to bind to every execution
     */
    public function __construct(
      ClientInterface $client,
      string $query,
      ?array $globalArgs = null
    ) {
        $this->client = $client;
        $this->rawQuery = $query;
        $this->globalArgs = (array) $globalArgs;
        $this->parts = $this->parseQuery($query);
    }

    /**
     * @param string $query The parameterised SOQL query
     * @return array Query fragments of either raw strings or arrays matching [{param}, 'quote' => bool] for placeholders
     */
    private function parseQuery(string $query): array
    {
        $placeholderIndex = 0;

        $parts = [];
        foreach (
            preg_split('~(::?\w+|\\?)~', $query, -1, \PREG_SPLIT_DELIM_CAPTURE)
            as $i => $part
        ) {
            if ($i % 2 === 1) {
                // Pattern
                $quote = true;
                if ($part === '?') {
                    $key = $placeholderIndex;
                    $placeholderIndex++;
                } else {
                    $key = substr($part, 1);
                    if ($key[0] === ':') {
                        // Unquoted string
                        $key = substr($key, 1);
                        $quote = false;
                    }
                }
                $part = [$key, 'quote' => $quote];
            }

            $parts[] = $part;
        }
        return $parts;
    }

    /** {@inheritdoc} */
    public function query(?array $args = null): RecordIterator
    {
        return $this->client->rawQuery($this->build($args));
    }

    /** {@inheritdoc} */
    public function queryAll(?array $args = null): array
    {
        // Phpforce library's `->queryAll` method is incorrectly identical to `->query` - manually convert to array
        $iterator = $this->client->rawQuery($this->build($args));
        return iterator_to_array($iterator);
    }

    /** {@inheritdoc} */
    public function queryOne(?array $args = null): ?SObject
    {
        $result = $this->query($args);
        if (count($result) === 0) {
            return null;
        }
        return $result->first();
    }

    /**
     * @param array|null $args Arguments to bind to the query
     * @return string The complete SOQL query with bound arguments
     * @throws \OutOfBoundsException An expected query parameter was not provided in $args
     */
    public function build(?array $args = null): string
    {
        $args = array_merge($this->globalArgs, (array) $args);

        $out = '';
        foreach ($this->parts as $part) {
            if (is_array($part)) {
                // Parameter
                $key = $part[0];
                $quote = $part['quote'] ?? true;
                if (!array_key_exists($key, $args)) {
                    throw new \OutOfBoundsException(
                        'Undefined query parameter "' . $key . '"',
                    );
                }

                $part = $args[$key];
                if (!$part instanceof SafeString) {
                    // Escape value
                    if (is_array($part)) {
                        $part = '( ' . SafeString::escapeArray($part) . ' )';
                    } else {
                        $part = SafeString::escape($part, false, $quote);
                    }
                }
            }

            $out .= $part;
        }
        return $out;
    }
}
