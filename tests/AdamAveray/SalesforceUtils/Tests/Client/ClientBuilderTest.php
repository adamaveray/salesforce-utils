<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Tests\Client;

use AdamAveray\SalesforceUtils\Client\ClientBuilder;
use AdamAveray\SalesforceUtils\Client\ClientInterface;
use Phpforce\SoapClient\Plugin\LogPlugin;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \AdamAveray\SalesforceUtils\Client\ClientBuilder
 */
class ClientBuilderTest extends \PHPUnit\Framework\TestCase
{
    private const DUMMY_WSDL_PATH =
        __DIR__ .
        '/../../../../../vendor/phpforce/soap-client/tests/Phpforce/SoapClient/Tests/Fixtures/sandbox.enterprise.wsdl.xml';

    /**
     * @covers ::__construct
     * @covers ::withLog
     * @covers ::build
     * @covers ::<!public>
     * @dataProvider buildDataProvider
     */
    public function testBuild(?LoggerInterface $log = null): void
    {
        $username = 'username';
        $password = 'password';
        $token = 'token';
        $options = ['test-option' => 'test-value'];

        $builder = new ClientBuilder(
            self::DUMMY_WSDL_PATH,
            $username,
            $password,
            $token,
            $options,
        );
        if ($log !== null) {
            $builder->withLog($log);
        }

        $client = $builder->build();

        self::assertInstanceOf(
            ClientInterface::class,
            $client,
            'A ClientInterface instance should be returned',
        );

        // Test SOAP client set
        $property = new \ReflectionProperty($client, 'soapClient');
        $property->setAccessible(true);
        /** @var \SoapClient $soapClient */
        $soapClient = $property->getValue($client);

        // Test auth properties
        self::assertInstanceOf(
            \SoapClient::class,
            $soapClient,
            'A SoapClient should be set on the generated Client',
        );
        $props = [
            'username' => $username,
            'password' => $password,
            'token' => $token,
        ];
        foreach ($props as $prop => $value) {
            $property = new \ReflectionProperty($client, $prop);
            $property->setAccessible(true);
            self::assertEquals(
                $value,
                $property->getValue($client),
                'The ' . $prop . ' should be set on the generated Client',
            );
        }

        // Test logging
        $dispatcher = $client->getEventDispatcher();
        foreach (LogPlugin::getSubscribedEvents() as $event => $method) {
            $listener = $dispatcher->getListeners($event)[0] ?? null;

            if ($log === null) {
                self::assertNull(
                    $listener,
                    'No logging events should be set if logger not set',
                );
                continue;
            }

            // Ensure listener correct format
            self::assertInstanceOf(
                LogPlugin::class,
                $listener[0] ?? null,
                'A LogPlugin instance callback for event "' .
                    $event .
                    '" should be set',
            );

            // Ensure listener is for specified method
            self::assertEquals(
                $method,
                $listener[1] ?? null,
                'The correct callback method for event "' .
                    $event .
                    '" should be set',
            );

            // Ensure listener will call specified logger
            $property = new \ReflectionProperty($listener[0], 'logger');
            $property->setAccessible(true);
            self::assertSame(
                $log,
                $property->getValue($listener[0]),
                'The LogPlugin instance should call the specified Log instance',
            );
        }
    }

    public function buildDataProvider(): iterable
    {
        yield 'No log' => [null];

        $log = $this->getMockForAbstractClass(LoggerInterface::class);
        yield 'With log' => [$log];
    }
}
