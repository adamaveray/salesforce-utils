<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Tests\Client;

use AdamAveray\SalesforceUtils\Client\Client;
use AdamAveray\SalesforceUtils\Client\ClientInterface;
use AdamAveray\SalesforceUtils\Queries\QueryInterface;
use AdamAveray\SalesforceUtils\Queries\SafeString;
use AdamAveray\SalesforceUtils\Writer;
use Phpforce\SoapClient\Plugin\LogPlugin;
use Phpforce\SoapClient\Result\DeleteResult;
use Phpforce\SoapClient\Result\QueryResult;
use Phpforce\SoapClient\Result\RecordIterator;
use Phpforce\SoapClient\Result\SObject;
use Phpforce\SoapClient\Result\UpsertResult;
use Phpforce\SoapClient\Soap\SoapClient;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \AdamAveray\SalesforceUtils\Client\Client
 */
class ClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return Client|MockObject
     */
    private function getClient(
        $methods = null,
        &$soapClient = null,
        &$username = 'username',
        &$password = 'password',
        &$token = 'token'
    ): Client {
        $soapClient = $this->getMockBuilder(SoapClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($methods === null) {
            // No mocking
            $client = new Client($soapClient, $username, $password, $token);
        } else {
            // Mock
            $client = $this->getMockBuilder(Client::class)
                ->setMethods($methods)
                ->setConstructorArgs([
                    $soapClient,
                    $username,
                    $password,
                    $token,
                ])
                ->getMock();
        }
        return $client;
    }

    /**
     * @covers ::prepare
     * @covers ::<!public>
     */
    public function testPrepare(): void
    {
        $soql = 'TEST QUERY';
        $args = ['a', 'b', 'c'];

        $client = $this->getClient(['rawQuery']);
        $query = $client->prepare($soql, $args);

        // Client assigned
        $property = new \ReflectionProperty($query, 'client');
        $property->setAccessible(true);
        self::assertEquals(
            $client,
            $property->getValue($query),
            'The client should be stored on the query correctly',
        );

        // Args assigned
        $property = new \ReflectionProperty($query, 'globalArgs');
        $property->setAccessible(true);
        self::assertEquals(
            $args,
            $property->getValue($query),
            'The global arguments should be stored on the query correctly',
        );

        // Calls rawQuery with assigned SOQL
        $iterator = new RecordIterator($client, new QueryResult());
        $client
            ->expects(self::once())
            ->method('rawQuery')
            ->with($soql)
            ->willReturn($iterator);
        self::assertEquals(
            $iterator,
            $query->query(),
            'The rawQuery return value should be passed through',
        );
    }

    /**
     * @covers ::rawQuery
     * @covers ::<!public>
     */
    public function testRawQuery(): void
    {
        $query = 'TEST SOQL';
        $queryResult = new QueryResult();

        $client = $this->getClient(['call']);
        $client
            ->expects(self::once())
            ->method('call')
            ->with('query', ['queryString' => $query])
            ->willReturn($queryResult);

        $result = $client->rawQuery($query);
        self::assertSame(
            $queryResult,
            $result->getQueryResult(),
            'The internal generated QueryResult should be set on the generated RecordIterator',
        );
    }

    /**
     * @covers ::__construct
     * @covers ::query
     * @covers ::queryAll
     * @covers ::queryOne
     * @covers ::<!public>
     * @dataProvider queriesDataProvider
     */
    public function testQueries(string $method, $expected): void
    {
        $args = ['a', 'b', 'c'];
        $client = $this->getClient();

        $query = $this->getMockBuilder(QueryInterface::class)
            ->setMethods([$method])
            ->getMockForAbstractClass();
        $query
            ->expects($this->exactly(2)) // Called for both tests
            ->method($method)
            ->with($args)
            ->willReturn($expected);

        // Prebuilt query
        $result = $client->{$method}($query, $args);
        self::assertSame(
            $expected,
            $result,
            'The Query method "' .
                $method .
                '" result should be passed through',
        );

        // Raw query string
        $string = 'TEST SOQL';
        $client = $this->getClient(['prepare']);
        $client
            ->expects(self::once())
            ->method('prepare')
            ->with($string)
            ->willReturn($query);

        $result = $client->{$method}($string, $args);
        self::assertSame(
            $expected,
            $result,
            'The Query method "' .
                $method .
                '" result should be passed through',
        );
    }

    public function queriesDataProvider(): iterable
    {
        $mockRecordIterator = $this->getMockBuilder(RecordIterator::class)
            ->disableOriginalConstructor()
            ->getMock();
        yield 'Method `query`' => ['query', $mockRecordIterator];
        yield 'Method `queryAll`' => ['queryAll', ['a', 'b', 'c']];
        yield 'Method `queryOne`' => ['queryOne', new SObject()];
    }

    /**
     * @covers ::escape
     * @covers ::<!public>
     * @dataProvider escapeDataProvider
     */
    public function testEscape(
        string $input,
        $isLike = null,
        $quote = null
    ): void {
        $isLike = $isLike ?? false;
        $quote = $quote ?? true;

        $expected = SafeString::escape($input, $isLike, $quote);

        $client = $this->getClient();
        $result = $client->escape($input, $isLike, $quote);

        self::assertEquals(
            $expected,
            $result,
            'An escaped SafeString should be generated',
        );
    }

    public function escapeDataProvider(): iterable
    {
        yield 'Plain' => ['""test value""'];
        yield 'Like' => ['""test value""', true];
        yield 'No-Quote' => ['""test value""', false, true];
    }

    /**
     * @covers ::describeSObject
     * @covers ::updateOne
     * @covers ::createOne
     * @covers ::deleteOne
     * @covers ::retrieveOne
     * @covers ::undeleteOne
     * @covers ::upsertOne
     * @covers ::<!public>
     * @dataProvider oneHelpersDataProvider
     */
    public function testOneHelpers(
        string $method,
        array $expectedArgs,
        array $callArgs,
        ?string $oneMethod = null
    ): void {
        $oneMethod = $oneMethod ?? $method . 'One';
        $mirror = new \ReflectionMethod(ClientInterface::class, $oneMethod);
        $returnType = $mirror->getReturnType()->getName();
        $expected = $this->getMockForAbstractClass($returnType);

        $client = $this->getClient([$method]);
        $client
            ->expects(self::once())
            ->method($method)
            ->with(...$expectedArgs)
            ->willReturn([$expected, 'b', 'c']);
        $result = $client->{$oneMethod}(...$callArgs);
        self::assertSame(
            $expected,
            $result,
            'The value from the parent method should be returned',
        );
    }

    public function oneHelpersDataProvider(): iterable
    {
        $type = 'testType';
        $id = '12345';
        $sObject = new SObject();
        $sObject->Id = $id;

        $genericObject = new \stdClass();

        yield 'Method `update`' => [
            'Method' => 'update',
            'Should Call' => [[$sObject], $type],
            'Call With' => [$sObject, $type],
        ];
        yield 'Method `create`' => [
            'Method' => 'create',
            'Should Call' => [[$sObject], $type],
            'Call With' => [$sObject, $type],
        ];
        yield 'Method `create` stdClass' => [
            'Method' => 'create',
            'Should Call' => [[$genericObject], $type],
            'Call With' => [$genericObject, $type],
        ];
        yield 'Method `delete`' => [
            'Method' => 'delete',
            'Should Call' => [[$id]],
            'Call With' => [$id],
        ];
        yield 'Method `retrieve`' => [
            'Method' => 'retrieve',
            'Should Call' => [['a', 'b', 'c'], [$id], $type],
            'Call With' => [['a', 'b', 'c'], $id, $type],
        ];
        yield 'Method `undelete`' => [
            'Method' => 'undelete',
            'Should Call' => [[$id]],
            'Call With' => [$id],
        ];
        yield 'Method `upsert`' => [
            'Method' => 'upsert',
            'Should Call' => ['externalIdFieldName', [$sObject], $type],
            'Call With' => ['externalIdFieldName', $sObject, $type],
        ];
        yield 'Method `describeSObject' => [
            'Method' => 'describeSObjects',
            'Should Call' => [[$type]],
            'Call With' => [$type],
            'Method One' => 'describeSObject',
        ];
    }

    /**
     * @covers ::createOne
     * @covers ::<!public>
     * @expectedException \InvalidArgumentException
     */
    public function testCreateInvalidSObject(): void
    {
        $client = $this->getClient();

        $object = new SObject();
        $client->createOne($object, 'type');
    }

    /**
     * @covers ::delete
     * @dataProvider deleteDataProvider
     */
    public function testDelete(array $ids, $callArg): void
    {
        $results = [new DeleteResult(), new DeleteResult(), new DeleteResult()];
        $property = new \ReflectionProperty(DeleteResult::class, 'success');
        $property->setAccessible(true);
        foreach ($results as $result) {
            $property->setValue($result, true);
        }

        $client = $this->getClient(['call']);
        $client
            ->expects(self::once())
            ->method('call')
            ->with('delete', ['ids' => $ids])
            ->willReturn($results);

        $result = $client->delete($callArg);

        self::assertSame(
            $results,
            $result,
            'The internal call results should be passed through',
        );
    }

    public function deleteDataProvider(): iterable
    {
        $ids = ['a', 'b', 'c'];

        yield 'Simple IDs' => [$ids, $ids];

        yield 'Objects' => [
            $ids,
            array_map(static function ($id): SObject {
                $item = new SObject();
                $item->Id = $id;
                return $item;
            }, $ids),
        ];
    }

    /**
     * @covers ::upsertOne
     * @covers ::<!public>
     */
    public function testUpsertOneAlternateOutput(): void
    {
        $externalIdFieldName = 'Test';
        $type = 'Contact';
        $rawOutput = new \stdClass();
        $rawOutput->success = true;
        $rawOutput->created = true;
        $rawOutput->id = 'xyz321';

        $expectedResult = new UpsertResult();
        foreach (
            [
                'success' => $rawOutput->success,
                'created' => $rawOutput->created,
                'id' => $rawOutput->id,
            ]
            as $propertyName => $value
        ) {
            $reflectionProperty = new \ReflectionProperty(
                $expectedResult,
                $propertyName,
            );
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($expectedResult, $value);
            $reflectionProperty->setAccessible(false);
        }

        $input = new SObject();
        $input->Id = 'abc123';
        $inputSoapVar = new \SoapVar(
            (object) ['Id' => $input->Id],
            \SOAP_ENC_OBJECT,
            $type,
        );

        $client = $this->getClient(['call']);
        $client
            ->expects(self::once())
            ->method('call')
            ->with('upsert', [
                'externalIDFieldName' => $externalIdFieldName,
                'sObjects' => [$inputSoapVar],
            ])
            ->willReturn([$rawOutput]);

        $result = $client->upsertOne($externalIdFieldName, $input, $type);

        self::assertEquals(
            $expectedResult,
            $result,
            'The raw object should be converted to an UpsertResult',
        );
    }

    /**
     * @covers ::getWriter
     * @covers ::<!public>
     */
    public function testGetWriter(): void
    {
        $client = $this->getClient();

        $result = $client->getWriter();

        $property = new \ReflectionProperty($result, 'client');
        $property->setAccessible(true);
        self::assertSame(
            $client,
            $property->getValue($result),
            'The client instance should be set on the Writer instance',
        );

        $result2 = $client->getWriter();
        self::assertSame(
            $result,
            $result2,
            'Multiple calls should return the same Writer instance',
        );
    }
}
