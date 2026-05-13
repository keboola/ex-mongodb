<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use MongoExtractor\Extractor;
use MongoExtractor\UriFactory;
use PHPUnit\Framework\TestCase;

class ExtractorAppendMongoshTimeoutsTest extends TestCase
{
    private UriFactory $uriFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uriFactory = new UriFactory();
    }

    public function testAddsBothTimeoutsToCleanUri(): void
    {
        $uri = $this->uriFactory->create([
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
        ]);

        $result = Extractor::appendMongoshTimeouts($uri);

        self::assertStringContainsString('connectTimeoutMS=10000', $result);
        self::assertStringContainsString('serverSelectionTimeoutMS=5000', $result);
        // Original URI is preserved
        self::assertStringStartsWith('mongodb://localhost:27017/myDatabase?', $result);
    }

    public function testAddsTimeoutsAfterExistingQueryParams(): void
    {
        $uri = $this->uriFactory->create([
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'myDatabase',
            'user' => 'user',
            'password' => 'pass',
            'authenticationDatabase' => 'authDb',
        ]);

        $result = Extractor::appendMongoshTimeouts($uri);

        // Existing authSource preserved, both timeouts appended
        self::assertStringContainsString('authSource=authDb', $result);
        self::assertStringContainsString('connectTimeoutMS=10000', $result);
        self::assertStringContainsString('serverSelectionTimeoutMS=5000', $result);
    }

    public function testDoesNotOverrideUserSuppliedServerSelectionTimeout(): void
    {
        // custom_uri with a user-supplied serverSelectionTimeoutMS
        $uri = $this->uriFactory->create([
            'protocol' => 'custom_uri',
            'uri' => 'mongodb://user@localhost:27017/db?serverSelectionTimeoutMS=20000',
            'password' => 'pass',
        ]);

        $result = Extractor::appendMongoshTimeouts($uri);

        // Existing value preserved (not duplicated)
        self::assertStringContainsString('serverSelectionTimeoutMS=20000', $result);
        self::assertStringNotContainsString('serverSelectionTimeoutMS=5000', $result);
        // connectTimeoutMS still added since user did not provide it
        self::assertStringContainsString('connectTimeoutMS=10000', $result);
    }

    public function testDoesNotOverrideUserSuppliedConnectTimeout(): void
    {
        $uri = $this->uriFactory->create([
            'protocol' => 'custom_uri',
            'uri' => 'mongodb://user@localhost:27017/db?connectTimeoutMS=30000',
            'password' => 'pass',
        ]);

        $result = Extractor::appendMongoshTimeouts($uri);

        self::assertStringContainsString('connectTimeoutMS=30000', $result);
        self::assertStringNotContainsString('connectTimeoutMS=10000', $result);
        self::assertStringContainsString('serverSelectionTimeoutMS=5000', $result);
    }

    public function testDoesNotDuplicateWhenBothAlreadyPresent(): void
    {
        $uri = $this->uriFactory->create([
            'protocol' => 'custom_uri',
            'uri' => 'mongodb://user@localhost:27017/db?connectTimeoutMS=15000&serverSelectionTimeoutMS=15000',
            'password' => 'pass',
        ]);

        $result = Extractor::appendMongoshTimeouts($uri);

        self::assertSame(1, substr_count($result, 'connectTimeoutMS='));
        self::assertSame(1, substr_count($result, 'serverSelectionTimeoutMS='));
        self::assertStringContainsString('connectTimeoutMS=15000', $result);
        self::assertStringContainsString('serverSelectionTimeoutMS=15000', $result);
    }
}
