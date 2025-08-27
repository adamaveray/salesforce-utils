<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Tests\Queries;

use AdamAveray\SalesforceUtils\Queries\Query;
use AdamAveray\SalesforceUtils\Queries\SafeString;
use AdamAveray\SalesforceUtils\Tests\DummyClasses\DummyRecordIterator;
use AdamAveray\SalesforceUtils\Client\ClientInterface;
use Phpforce\SoapClient\Result\QueryResult;
use Phpforce\SoapClient\Result\SObject;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \AdamAveray\SalesforceUtils\Queries\Query
 */
class QueryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array|null $methods
     * @return MockObject|ClientInterface
     */
    private function getClient(?array $methods = null): ClientInterface
    {
        $builder = $this->getMockBuilder(ClientInterface::class);
        if ($methods !== null) {
            $builder->setMethods($methods);
        }
        return $builder->getMockForAbstractClass();
    }

    /**
     * @param string $string
     * @param array $values
     * @param null $iterator
     * @return ClientInterface|MockObject
     */
    private function getQueryClient(
        string $string,
        array $values,
        &$iterator = null
    ): ClientInterface {
        $iterator = new DummyRecordIterator($values);

        $client = $this->getClient(['query']);
        $client
            ->expects($this->once())
            ->method('rawQuery')
            ->with($string)
            ->willReturn($iterator);
        return $client;
    }

    /**
     * @covers ::__construct
     * @covers ::build
     * @covers ::<!public>
     * @dataProvider buildDataProvider
     */
    public function testBuild(
        string $expected,
        string $rawQuery,
        $globalArgs = null,
        $thisArgs = null
    ): void {
        $query = new Query($this->getClient(), $rawQuery, $globalArgs);

        $method = new \ReflectionMethod($query, 'build');
        $method->setAccessible(true);
        $result = $method->invoke($query, $thisArgs);

        self::assertEquals(
            $expected,
            $result,
            'The query should be processed and build correctly',
        );
    }

    public function buildDataProvider(): iterable
    {
        yield 'No params' => ['TEST SOQL', 'TEST SOQL'];

        yield 'Global params' => [
            'TEST SOQL \'test param one\' \'test param two\'',
            'TEST SOQL :one :two',
            [
                'one' => 'test param one',
                'two' => 'test param two',
            ],
        ];

        yield 'Local params' => [
            'TEST SOQL \'test param one\' \'test param two\'',
            'TEST SOQL :one :two',
            null,
            [
                'one' => 'test param one',
                'two' => 'test param two',
            ],
        ];

        yield 'Mixed params' => [
            'TEST SOQL \'global param one\' \'local param two\'',
            'TEST SOQL :one :two',
            [
                'one' => 'global param one',
                'two' => 'global param two',
            ],
            [
                'two' => 'local param two',
            ],
        ];

        yield 'Unquoted param' => [
            'TEST SOQL param one',
            'TEST SOQL ::one',
            [
                'one' => 'param one',
            ],
        ];

        yield 'Safe string param' => [
            'TEST SOQL "special' . "\t" . 'chars"',
            'TEST SOQL :one',
            [
                'one' => new SafeString('"special' . "\t" . 'chars"'),
            ],
        ];

        yield 'Array param' => [
            'TEST SOQL IN ( \'item one\', \'item two\', \'item three\' )',
            'TEST SOQL IN :one',
            [
                'one' => ['item one', 'item two', 'item three'],
            ],
        ];

        yield 'Anonymous params' => [
            'TEST SOQL \'param one\' \'param two\'',
            'TEST SOQL ? ?',
            ['param one', 'param two'],
        ];
    }

    /**
     * @covers ::build
     * @depends testBuild
     * @expectedException \OutOfBoundsException
     */
    public function testBuildMissingParams()
    {
        $this->testBuild('', 'TEST :param', [], []);
    }

    /**
     * @covers ::query
     * @depends testBuild
     */
    public function testQuery(): void
    {
        $string = 'SOQL QUERY TEST';
        $stringRaw = 'SOQL QUERY ::test';
        $args = ['test' => 'TEST'];

        $client = $this->getQueryClient($string, ['a', 'b', 'c'], $expected);
        $query = new Query($client, $stringRaw);

        $result = $query->query($args);
        self::assertSame(
            $expected,
            $result,
            'The result of ClientInterface::query() should be passed through',
        );
    }

    /**
     * @covers ::queryAll
     * @depends testBuild
     */
    public function testQueryAll(): void
    {
        $values = ['a', 'b', 'c'];

        $string = 'SOQL QUERY TEST';
        $stringRaw = 'SOQL QUERY ::test';
        $args = ['test' => 'TEST'];

        $client = $this->getQueryClient($string, $values);
        $query = new Query($client, $stringRaw);

        $result = $query->queryAll($args);
        self::assertSame(
            $values,
            $result,
            'The iterator’s values should be passed through',
        );
    }

    /**
     * @covers ::queryOne
     * @depends testBuild
     */
    public function testQueryOne(): void
    {
        $expected = new SObject();

        $string = 'SOQL QUERY TEST';
        $stringRaw = 'SOQL QUERY ::test';
        $args = ['test' => 'TEST'];

        // With results
        $client = $this->getQueryClient($string, [$expected, 'b', 'c']);
        $query = new Query($client, $stringRaw);

        $result = $query->queryOne($args);
        self::assertSame(
            $expected,
            $result,
            'The result of ClientInterface::query() should be passed through',
        );

        // No results
        $client = $this->getQueryClient($string, []);
        $query = new Query($client, $stringRaw);

        $result = $query->queryOne($args);
        self::assertNull($result, 'Null should be returned when no results');
    }
}
