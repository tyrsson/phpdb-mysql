<?php

declare(strict_types=1);

namespace PhpDbTest\Mysql\Platform;

use Override;
use PhpDb\Adapter\Driver\Pdo\Result;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Mysql\AdapterPlatform;
use PhpDb\Mysql\Pdo\Connection;
use PhpDb\Mysql\Pdo\Driver;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversMethod(AdapterPlatform::class, 'getName')]
#[CoversMethod(AdapterPlatform::class, 'getQuoteIdentifierSymbol')]
#[CoversMethod(AdapterPlatform::class, 'quoteIdentifier')]
#[CoversMethod(AdapterPlatform::class, 'quoteIdentifierChain')]
#[CoversMethod(AdapterPlatform::class, 'getQuoteValueSymbol')]
#[CoversMethod(AdapterPlatform::class, 'quoteValue')]
#[CoversMethod(AdapterPlatform::class, 'quoteTrustedValue')]
#[CoversMethod(AdapterPlatform::class, 'quoteValueList')]
#[CoversMethod(AdapterPlatform::class, 'getIdentifierSeparator')]
#[CoversMethod(AdapterPlatform::class, 'quoteIdentifierInFragment')]
final class AdapterPlatformTest extends TestCase
{
    protected AdapterPlatform $platform;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    #[Override]
    protected function setUp(): void
    {
        $pdo            = new Driver(
            $this->createMock(Connection::class),
            $this->createMock(Statement::class),
            $this->createMock(Result::class),
        );
        $this->platform = new AdapterPlatform($pdo);
    }

    #[Test]
    public function getName(): void
    {
        self::assertEquals('MySQL', $this->platform->getName());
    }

    #[Test]
    public function getQuoteIdentifierSymbol(): void
    {
        self::assertEquals('`', $this->platform->getQuoteIdentifierSymbol());
    }

    #[Test]
    public function quoteIdentifier(): void
    {
        self::assertEquals('`identifier`', $this->platform->quoteIdentifier('identifier'));
        self::assertEquals('`ident``ifier`', $this->platform->quoteIdentifier('ident`ifier'));
        self::assertEquals('`namespace:$identifier`', $this->platform->quoteIdentifier('namespace:$identifier'));
    }

    #[Test]
    public function quoteIdentifierChain(): void
    {
        self::assertEquals('`identifier`', $this->platform->quoteIdentifierChain('identifier'));
        self::assertEquals('`identifier`', $this->platform->quoteIdentifierChain(['identifier']));
        self::assertEquals('`schema`.`identifier`', $this->platform->quoteIdentifierChain(['schema', 'identifier']));

        self::assertEquals('`ident``ifier`', $this->platform->quoteIdentifierChain('ident`ifier'));
        self::assertEquals('`ident``ifier`', $this->platform->quoteIdentifierChain(['ident`ifier']));
        self::assertEquals(
            '`schema`.`ident``ifier`',
            $this->platform->quoteIdentifierChain(['schema', 'ident`ifier'])
        );
    }

    #[Test]
    public function getQuoteValueSymbol(): void
    {
        self::assertEquals("'", $this->platform->getQuoteValueSymbol());
    }

    #[Test]
    public function quoteValueRaisesNoticeWithoutPlatformSupport(): void
    {
        /**
         * todo: Determine if vulnerability warning is required during unit testing
         *
         * todo: This testing needs expanded to cover all possible driver types
         * since using \PDO currently causes a TypeError to be raised due to the
         * underlying quoteViaDriver method returning false instead of ?string
         */
        //$this->expectNotice();
        //$this->expectExceptionMessage(
        //    'Attempting to quote a value in PhpDb\Adapter\Platform\Mysql without extension/driver support can '
        //    . 'introduce security vulnerabilities in a production environment'
        //);
        $this->expectNotToPerformAssertions();
        $this->platform->quoteValue('value');
    }

    #[Test]
    public function quoteValue(): void
    {
        self::assertEquals("'value'", @$this->platform->quoteValue('value'));
        self::assertEquals("'Foo O\\'Bar'", @$this->platform->quoteValue("Foo O'Bar"));
        self::assertEquals(
            '\'\\\'; DELETE FROM some_table; -- \'',
            @$this->platform->quoteValue('\'; DELETE FROM some_table; -- ')
        );
        self::assertEquals(
            "'\\\\\\'; DELETE FROM some_table; -- '",
            @$this->platform->quoteValue('\\\'; DELETE FROM some_table; -- ')
        );
    }

    #[Test]
    public function quoteTrustedValue(): void
    {
        self::assertEquals("'value'", $this->platform->quoteTrustedValue('value'));
        self::assertEquals("'Foo O\\'Bar'", $this->platform->quoteTrustedValue("Foo O'Bar"));
        self::assertEquals(
            '\'\\\'; DELETE FROM some_table; -- \'',
            $this->platform->quoteTrustedValue('\'; DELETE FROM some_table; -- ')
        );

        //                   '\\\'; DELETE FROM some_table; -- '  <- actual below
        self::assertEquals(
            "'\\\\\\'; DELETE FROM some_table; -- '",
            $this->platform->quoteTrustedValue('\\\'; DELETE FROM some_table; -- ')
        );
    }

    #[Test]
    public function quoteValueList(): void
    {
        /**
         * @todo Determine if vulnerability warning is required during unit testing
         */
        //$this->expectError();
        //$this->expectExceptionMessage(
        //    'Attempting to quote a value in PhpDb\Adapter\Platform\Mysql without extension/driver support can '
        //    . 'introduce security vulnerabilities in a production environment'
        //);
        self::assertEquals("'Foo O\\'Bar'", $this->platform->quoteValueList("Foo O'Bar"));
    }

    #[Test]
    public function getIdentifierSeparator(): void
    {
        self::assertEquals('.', $this->platform->getIdentifierSeparator());
    }

    #[Test]
    public function quoteIdentifierInFragment(): void
    {
        self::assertEquals('`foo`.`bar`', $this->platform->quoteIdentifierInFragment('foo.bar'));
        self::assertEquals('`foo` as `bar`', $this->platform->quoteIdentifierInFragment('foo as bar'));
        self::assertEquals('`$TableName`.`bar`', $this->platform->quoteIdentifierInFragment('$TableName.bar'));
        self::assertEquals(
            '`cmis:$TableName` as `cmis:TableAlias`',
            $this->platform->quoteIdentifierInFragment('cmis:$TableName as cmis:TableAlias')
        );

        $this->assertEquals(
            '`foo-bar`.`bar-foo`',
            $this->platform->quoteIdentifierInFragment('foo-bar.bar-foo')
        );
        $this->assertEquals(
            '`foo-bar` as `bar-foo`',
            $this->platform->quoteIdentifierInFragment('foo-bar as bar-foo')
        );
        $this->assertEquals(
            '`$TableName-$ColumnName`.`bar-foo`',
            $this->platform->quoteIdentifierInFragment('$TableName-$ColumnName.bar-foo')
        );
        $this->assertEquals(
            '`cmis:$TableName-$ColumnName` as `cmis:TableAlias-ColumnAlias`',
            $this->platform->quoteIdentifierInFragment('cmis:$TableName-$ColumnName as cmis:TableAlias-ColumnAlias')
        );

        // single char words
        self::assertEquals(
            '(`foo`.`bar` = `boo`.`baz`)',
            $this->platform->quoteIdentifierInFragment('(foo.bar = boo.baz)', ['(', ')', '='])
        );
        self::assertEquals(
            '(`foo`.`bar`=`boo`.`baz`)',
            $this->platform->quoteIdentifierInFragment('(foo.bar=boo.baz)', ['(', ')', '='])
        );
        self::assertEquals('`foo`=`bar`', $this->platform->quoteIdentifierInFragment('foo=bar', ['=']));

        $this->assertEquals(
            '(`foo-bar`.`bar-foo` = `boo-baz`.`baz-boo`)',
            $this->platform->quoteIdentifierInFragment('(foo-bar.bar-foo = boo-baz.baz-boo)', ['(', ')', '='])
        );
        $this->assertEquals(
            '(`foo-bar`.`bar-foo`=`boo-baz`.`baz-boo`)',
            $this->platform->quoteIdentifierInFragment('(foo-bar.bar-foo=boo-baz.baz-boo)', ['(', ')', '='])
        );
        $this->assertEquals(
            '`foo-bar`=`bar-foo`',
            $this->platform->quoteIdentifierInFragment('foo-bar=bar-foo', ['='])
        );

        // case insensitive safe words
        self::assertEquals(
            '(`foo`.`bar` = `boo`.`baz`) AND (`foo`.`baz` = `boo`.`baz`)',
            $this->platform->quoteIdentifierInFragment(
                '(foo.bar = boo.baz) AND (foo.baz = boo.baz)',
                ['(', ')', '=', 'and']
            )
        );

        $this->assertEquals(
            '(`foo-bar`.`bar-foo` = `boo-baz`.`baz-boo`) AND (`foo-baz`.`baz-foo` = `boo-baz`.`baz-boo`)',
            $this->platform->quoteIdentifierInFragment(
                '(foo-bar.bar-foo = boo-baz.baz-boo) AND (foo-baz.baz-foo = boo-baz.baz-boo)',
                ['(', ')', '=', 'and']
            )
        );

        // case insensitive safe words in field
        self::assertEquals(
            '(`foo`.`bar` = `boo`.baz) AND (`foo`.baz = `boo`.baz)',
            $this->platform->quoteIdentifierInFragment(
                '(foo.bar = boo.baz) AND (foo.baz = boo.baz)',
                ['(', ')', '=', 'and', 'bAz']
            )
        );

        // case insensitive safe words in field
        $this->assertEquals(
            '(`foo-bar`.`bar-foo` = `boo-baz`.baz-boo) AND (`foo-baz`.`baz-foo` = `boo-baz`.baz-boo)',
            $this->platform->quoteIdentifierInFragment(
                '(foo-bar.bar-foo = boo-baz.baz-boo) AND (foo-baz.baz-foo = boo-baz.baz-boo)',
                ['(', ')', '=', 'and', 'bAz-BOo']
            )
        );
    }
}
