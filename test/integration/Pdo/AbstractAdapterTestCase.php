<?php

declare(strict_types=1);

namespace PhpDbIntegrationTest\Mysql\Pdo;

use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\SchemaAwareInterface;
use PhpDb\Mysql\Pdo\Driver;
use PhpDbIntegrationTest\Mysql\Container\TestAsset\SetupTrait;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversMethod(Adapter::class, 'getCurrentSchema')]
#[CoversMethod(AdapterInterface::class, '__construct')]
#[CoversMethod(SchemaAwareInterface::class, 'getCurrentSchema')]
#[CoversMethod(ConnectionInterface::class, 'connect')]
#[CoversMethod(ConnectionInterface::class, 'disconnect')]
#[CoversMethod(ConnectionInterface::class, 'isConnected')]
abstract class AbstractAdapterTestCase extends TestCase
{
    use SetupTrait;

    #[Test]
    public function connection(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    #[Test]
    public function getCurrentSchema(): void
    {
        /** @var AdapterInterface&SchemaAwareInterface&Adapter $adapter */
        $adapter = $this->getAdapter();
        $schema  = $adapter->getCurrentSchema();
        self::assertIsString($schema);
        self::assertNotEmpty($schema);
    }

    #[Test]
    public function driverDisconnectAfterQuoteWithPlatform(): void
    {
        $isTcpConnection = $this->isTcpConnection();

        /** @var AdapterInterface&Adapter $adapter */
        $adapter = $this->getAdapter([
            'db' => [
                'driver' => Driver::class,
            ],
        ]);
        $adapter->getDriver()->getConnection()->connect();
        self::assertTrue($adapter->getDriver()->getConnection()->isConnected());
        if ($isTcpConnection) {
            self::assertTrue($adapter->getDriver()->getConnection()->isConnected());
        }

        $adapter->getDriver()->getConnection()->disconnect();
        self::assertFalse($adapter->getDriver()->getConnection()->isConnected());
        if ($isTcpConnection) {
            self::assertFalse($adapter->getDriver()->getConnection()->isConnected());
        }

        $adapter->getDriver()->getConnection()->connect();
        self::assertTrue($adapter->getDriver()->getConnection()->isConnected());
        if ($isTcpConnection) {
            self::assertTrue($adapter->getDriver()->getConnection()->isConnected());
        }

        $adapter->getPlatform()->quoteValue('test');

        $adapter->getDriver()->getConnection()->disconnect();

        self::assertFalse($adapter->getDriver()->getConnection()->isConnected());
        if ($isTcpConnection) {
            self::assertFalse($adapter->getDriver()->getConnection()->isConnected());
        }
    }

    protected function isTcpConnection(): bool
    {
        $hostName = $this->getHostname();
        return 'localhost' !== $hostName && '127.0.0.1' !== $hostName;
    }
}
