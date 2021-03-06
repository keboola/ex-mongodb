<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use MongoExtractor\ExportCommandFactory;
use MongoExtractor\UriFactory;
use PHPUnit\Framework\TestCase;

class ExportCommandFactoryTest extends TestCase
{
    private ExportCommandFactory $commandFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $uriFactory = new UriFactory();
        $this->commandFactory = new ExportCommandFactory($uriFactory, false);
    }

    public function testCreateMinimal(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable
        $this->assertSame($expectedCommand, $command);
    }

    public function testMongoDbProtocol(): void
    {
        $options = [
            'protocol' => 'mongodb',
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testMongoDbSrvProtocol(): void
    {
        $options = [
            'protocol' => 'mongodb+srv',
            'host' => 'localhost',
            'port' => 123456,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
        ];

        $command = $this->commandFactory->create($options);

        // URI starting with mongodb+srv:// must not include a port number
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --uri 'mongodb+srv://localhost/myDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testMongoDbSrvProtocolEmptyPort(): void
    {
        $options = [
            'protocol' => 'mongodb+srv',
            'host' => 'localhost',
            'port' => '',
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
        ];
        $command = $this->commandFactory->create($options);

        // URI starting with mongodb+srv:// must not include a port number
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --uri 'mongodb+srv://localhost/myDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testWithCustomAuthenticationDatabase(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
            // auth with custom auth database
            'user' => 'user',
            'password' => 'pass',
            'authenticationDatabase' => 'myAuthDatabase',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --username 'user' --password 'pass' --authenticationDatabase 'myAuthDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testMongoDbSrvProtocolWithCustomAuthenticationDatabase(): void
    {
        $options = [
            'protocol' => 'mongodb+srv',
            'host' => 'localhost',
            'port' => 123456,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
            'user' => 'user',
            'password' => 'pass',
            'authenticationDatabase' => 'myAuthDatabase',
        ];

        $command = $this->commandFactory->create($options);

        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --uri 'mongodb+srv://user:pass@localhost/myDatabase?authSource=myAuthDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable
        $this->assertSame($expectedCommand, $command);
    }

    public function testWithEmptyCustomAuthenticationDatabase(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
            // auth with empty custom auth database
            'user' => 'user',
            'password' => 'pass',
            'authenticationDatabase' => ' ',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --username 'user' --password 'pass' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testCreateFull(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'user' => 'user',
            'password' => 'pass',
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'query' => '{a: "b"}',
            'sort' => '{a: 1, b: -1}',
            'limit' => 10,
            'out' => '/tmp/create-test.json',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --username 'user' --password 'pass' --collection 'myCollection' --query '{"a": "b"}' --sort '{a: 1, b: -1}' --limit '10' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testWithEmptyOptionalValues(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'user' => 'user',
            'password' => 'pass',
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'query' => '',
            'sort' => ' ',
            'limit' => '  ',
            'out' => '/tmp/create-test.json',
        ];

        $command = $this->commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --username 'user' --password 'pass' --collection 'myCollection' --sort '{_id: 1}' --type 'json'
BASH;
        // phpcs:enable

        $this->assertSame($expectedCommand, $command);
    }

    public function testQuietTrue(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'collection' => 'myCollection',
            'out' => '/tmp/create-test.json',
        ];

        $commandFactory = new ExportCommandFactory(new UriFactory(), true);
        $command = $commandFactory->create($options);
        // phpcs:disable Generic.Files.LineLength.MaxExceeded
        $expectedCommand = <<<BASH
mongoexport --host 'localhost' --port '27017' --db 'myDatabase' --collection 'myCollection' --sort '{_id: 1}' --type 'json' --quiet
BASH;
        // phpcs:enable
        $this->assertSame($expectedCommand, $command);
    }
}
