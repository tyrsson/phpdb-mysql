<?php

declare(strict_types=1);

namespace PhpDbIntegrationTest\Mysql\Pdo;

use PDO;
use PhpDb\Adapter\Driver\AbstractConnection;
use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\Driver\Pdo\AbstractPdoConnection;
use PhpDb\Adapter\Driver\Pdo\Result;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Adapter\Driver\PdoConnectionInterface;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Adapter\Driver\StatementInterface;
use PhpDb\Mysql\Pdo\Connection;
use PhpDbIntegrationTest\Mysql\Container\TestAsset\SetupTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
#[Group('integration-pdo')]
#[CoversClass(Connection::class)]
#[CoversMethod(Connection::class, 'prepare')]
#[CoversClass(ConnectionInterface::class)]
#[CoversMethod(ConnectionInterface::class, 'execute')]
#[CoversMethod(ConnectionInterface::class, 'getResource')]
#[CoversMethod(ConnectionInterface::class, 'getLastGeneratedValue')]
final class ConnectionTest extends TestCase
{
    use SetupTrait;

    #[Test]
    public function getResource(): void
    {
        $connection = $this->getAdapter()->getDriver()->getConnection();
        self::assertInstanceOf(PDO::class, $connection->getResource());
    }

    #[Test]
    public function execute(): void
    {
        $connection = $this->getAdapter()->getDriver()->getConnection();
        /** @var ResultInterface&Result $result */
        $result = $connection->execute('SELECT \'foo\'');
        self::assertInstanceOf(ResultInterface::class, $result);
        self::assertInstanceOf(Result::class, $result);
    }

    #[Test]
    public function prepare(): void
    {
        /** @var ConnectionInterface&PdoConnectionInterface&AbstractConnection&AbstractPdoConnection&Connection $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        /** @var StatementInterface&Statement $statement */
        $statement = $connection->prepare('SELECT \'foo\'');
        self::assertInstanceOf(StatementInterface::class, $statement);
        self::assertInstanceOf(Statement::class, $statement);
    }

    #[Test]
    public function getLastGeneratedValue(): void
    {
        /** @var ConnectionInterface&PdoConnectionInterface&AbstractConnection&AbstractPdoConnection&Connection $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();
        $lastId = (int) $connection->getLastGeneratedValue();
        self::assertIsInt($lastId);
        $connection->disconnect();
    }

    #[Test]
    public function connectMethodReturnsConnectionInterface(): void
    {
        /** @var ConnectionInterface&PdoConnectionInterface&AbstractConnection&AbstractPdoConnection&Connection $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        self::assertInstanceOf(ConnectionInterface::class, $connection->connect());
        $connection->disconnect();
    }

    #[Test]
    public function beginTransaction(): void
    {
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();

        self::assertTrue($connection->isConnected());
        self::assertFalse($connection->inTransaction());

        $result = $connection->beginTransaction();

        self::assertInstanceOf(Connection::class, $result);
        self::assertTrue($connection->inTransaction());

        $connection->rollback();
        self::assertFalse($connection->inTransaction());
        $connection->disconnect();
    }

    #[Test]
    public function commit(): void
    {
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();
        self::assertTrue($connection->isConnected());

        $connection->beginTransaction();
        self::assertTrue($connection->inTransaction());

        $connection->execute("INSERT INTO test (name, value) VALUES ('tx_commit', 'test')");

        $result = $connection->commit();
        self::assertInstanceOf(Connection::class, $result);
        self::assertFalse($connection->inTransaction());

        $result = $connection->execute("SELECT COUNT(*) AS cnt FROM test WHERE name = 'tx_commit'");
        self::assertSame(1, $result->getResource()->fetchColumn());

        $connection->execute("DELETE FROM test WHERE name = 'tx_commit'");
        $connection->disconnect();
    }

    #[Test]
    public function rollback(): void
    {
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();
        self::assertTrue($connection->isConnected());

        $connection->beginTransaction();
        self::assertTrue($connection->inTransaction());

        $connection->execute("INSERT INTO test (name, value) VALUES ('tx_rollback', 'test')");

        $result = $connection->rollback();
        self::assertInstanceOf(Connection::class, $result);
        self::assertFalse($connection->inTransaction());

        $result = $connection->execute("SELECT COUNT(*) AS cnt FROM test WHERE name = 'tx_rollback'");
        self::assertSame(0, $result->getResource()->fetchColumn());

        $connection->disconnect();
    }

    #[Test]
    public function autocommitRestoredAfterCommit(): void
    {
        /** @var Connection $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();
        self::assertTrue($connection->isConnected());

        $connection->beginTransaction();
        self::assertTrue($connection->inTransaction());
        $connection->commit();
        self::assertFalse($connection->inTransaction());

        $connection->execute("INSERT INTO test (name, value) VALUES ('tx_autocommit', 'test')");

        $connection->disconnect();

        $connection->connect();
        $result = $connection->execute("SELECT COUNT(*) AS cnt FROM test WHERE name = 'tx_autocommit'");
        self::assertSame(1, $result->getResource()->fetchColumn());

        $connection->execute("DELETE FROM test WHERE name = 'tx_autocommit'");
        $connection->disconnect();
    }

    #[Test]
    public function autocommitRestoredAfterRollback(): void
    {
        /** @var Connection $connection */
        $connection = $this->getAdapter()->getDriver()->getConnection();
        $connection->connect();
        self::assertTrue($connection->isConnected());

        $connection->beginTransaction();
        self::assertTrue($connection->inTransaction());
        $connection->rollback();
        self::assertFalse($connection->inTransaction());

        $connection->execute("INSERT INTO test (name, value) VALUES ('tx_autocommit_rb', 'test')");

        $connection->disconnect();

        $connection->connect();
        $result = $connection->execute("SELECT COUNT(*) AS cnt FROM test WHERE name = 'tx_autocommit_rb'");
        self::assertSame(1, $result->getResource()->fetchColumn());

        $connection->execute("DELETE FROM test WHERE name = 'tx_autocommit_rb'");
        $connection->disconnect();
    }
}
