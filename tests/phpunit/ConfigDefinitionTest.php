<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use Generator;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ConfigRowDefinition;
use MongoExtractor\Config\DbNode;
use MongoExtractor\Config\OldConfigDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigRowDefinitionTest extends TestCase
{
    /**
     * @param array<string, mixed> $configData
     * @dataProvider validOldConfigsData
     */
    public function testValidOldConfig(array $configData): void
    {
        $config = new Config($configData, new OldConfigDefinition());
        $configData = $this->addDefaultValues($configData, true);
        $this->assertEquals($configData, $config->getData());
    }

    /**
     * @param array<string, mixed> $configData
     * @dataProvider validConfigsData
     */
    public function testValidConfigRow(array $configData): void
    {
        $config = new Config($configData, new ConfigRowDefinition());
        $configData = $this->addDefaultValues($configData);
        $this->assertEquals($configData, $config->getData());
    }

    /**
     * @param array<string, mixed> $configData
     * @dataProvider invalidConfigsData
     */
    public function testInvalidConfigs(array $configData, string $expectedError): void
    {
        try {
            new Config($configData, new ConfigRowDefinition());
            $this->fail('Validation should produce error');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString($expectedError, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $configData
     * @dataProvider invalidOldConfigsData
     */
    public function testInvalidOldConfigs(array $configData, string $expectedError): void
    {
        try {
            new Config($configData, new OldConfigDefinition());
            $this->fail('Validation should produce error');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString($expectedError, $e->getMessage());
        }
    }

    /**
     * @return Generator<string, array<string, mixed>>
     */
    public function validOldConfigsData(): Generator
    {
        yield 'valid config' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'query' => '{borough: "Bronx"}',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
        ];

        yield 'valid config with protocol' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'mongodb+srv',
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'query' => '{borough: "Bronx"}',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
        ];

        yield 'valid config with authenticationMechanism' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => 'SCRAM-SHA-256',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'mapping' => ['_id' => null],
                                    ],
                            ],
                    ],
            ],
        ];

        yield 'valid config with empty authenticationMechanism' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => '',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'mapping' => ['_id' => null],
                                    ],
                            ],
                    ],
            ],
        ];

        yield 'incremental fetching column normalization' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'collection' => 'restaurants',
                                        'incrementalFetchingColumn' => 'someColumn',
                                    ],
                                1 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'collection' => 'restaurants',
                                        'incrementalFetchingColumn' => 'someColumn.\$date',
                                    ],
                                2 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'collection' => 'restaurants',
                                        'incrementalFetchingColumn' => 'someColumn.nested.\$date',
                                    ],
                            ],
                    ],
            ],
        ];
    }

    /**
     * @return Generator<string, array<string, mixed>>
     */
    public function invalidOldConfigsData(): Generator
    {
        yield 'config row' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'export' =>
                            [
                                'name' => 'bronx-bakeries',
                                'id' => 123,
                                'collection' => 'restaurants',
                                'query' => '{borough: "Bronx"}',
                                'incremental' => false,
                                'mapping' =>
                                    [
                                        '_id' => null,
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'Unrecognized option "export" under "root.parameters". Did you mean "exports"?',
        ];

        yield 'missing keys' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'collection' => 'restaurants',
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'The child node "database" at path "parameters.db" must be configured.',
        ];

        yield 'missing uri' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'custom_uri',
                                'database' => 'db',
                                'password' => 'pass',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'collection' => 'restaurants',
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'The child node "uri" at path "parameters.db" must be configured.',
        ];

        yield 'invalid protocol' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'mongodb+error',
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'query' => '{borough: "Bronx"}',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'The value "mongodb+error" is not allowed for path "root.parameters.db.protocol".'
                . ' Permissible values: "mongodb", "mongodb+srv", "custom_uri"',
        ];

        yield 'invalid incremental fetching config 1' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'query' => '{borough: "Bronx"}',
                                        'incrementalFetchingColumn' => 'borough',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'Both incremental fetching and query cannot be set together.',
        ];

        yield 'invalid incremental fetching config 2' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'sort' => '_id',
                                        'incrementalFetchingColumn' => 'borough',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'Both incremental fetching and sort cannot be set together.',
        ];

        yield 'invalid authenticationMechanism value' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => 'INVALID-MECHANISM',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'mapping' => ['_id' => null],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'The value "INVALID-MECHANISM" is not allowed for path '
                . '"root.parameters.db.authenticationMechanism".',
        ];

        yield 'authenticationMechanism not allowed with custom_uri' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'custom_uri',
                                'uri' => 'mongodb://user@localhost/db',
                                'password' => 'pass',
                                'authenticationMechanism' => 'SCRAM-SHA-256',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'mapping' => ['_id' => null],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'Configuration node "db.authenticationMechanism" '
                . 'is not compatible with custom URI.',
        ];
    }

    /**
     * @return Generator<string, array<string, mixed>>
     */
    public function validConfigsData(): Generator
    {
        yield 'valid config' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'collection' => 'restaurants',
                            'tableName' => 'bronx-bakeries',
                            'query' => '{borough: "Bronx"}',
                            'incremental' => false,
                            'mapping' =>
                                [
                                    '_id' => null,
                                ],
                    ],
            ],
        ];

        yield 'valid config with protocol' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'mongodb+srv',
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                            'query' => '{borough: "Bronx"}',
                            'incremental' => false,
                            'mapping' =>
                                [
                                    '_id' => null,
                                ],
                    ],
            ],
        ];

        yield 'incremental fetching column normalization' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                            'incrementalFetchingColumn' => 'someColumn',
                    ],
            ],
        ];

        yield 'valid row config with authenticationMechanism' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => 'SCRAM-SHA-256',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
        ];

        yield 'valid row config with empty authenticationMechanism' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => '',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
        ];
    }

    /**
     * @return Generator<string, array<string, mixed>>
     */
    public function invalidConfigsData(): Generator
    {
        yield 'old config' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                        'exports' =>
                            [
                                0 =>
                                    [
                                        'name' => 'bronx-bakeries',
                                        'id' => 123,
                                        'collection' => 'restaurants',
                                        'query' => '{borough: "Bronx"}',
                                        'incremental' => false,
                                        'mapping' =>
                                            [
                                                '_id' => null,
                                            ],
                                    ],
                            ],
                    ],
            ],
            'expectedError' => 'Unrecognized option "exports" under "root.parameters". Available options are ' .
                '"collection", "db", "includeParentInPK", "incremental", "incrementalFetchingColumn"' .
                ', "incrementalFetchingOverlap", "limit", "mapping", "mode", "query", "quiet", "sort", "tableName".',
        ];

        yield 'missing keys' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
            'expectedError' => 'The child node "database" at path "parameters.db" must be configured.',
        ];

        yield 'missing uri' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'custom_uri',
                                'database' => 'db',
                                'password' => 'pass',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
            'expectedError' => 'The child node "uri" at path "parameters.db" must be configured.',
        ];

        yield 'invalid protocol' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'mongodb+error',
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                            'query' => '{borough: "Bronx"}',
                            'incremental' => false,
                            'mapping' =>
                                [
                                    '_id' => null,
                                ],
                    ],
            ],
            'expectedError' => 'The value "mongodb+error" is not allowed for path "root.parameters.db.protocol".'
                . ' Permissible values: "mongodb", "mongodb+srv", "custom_uri"',
        ];

        yield 'invalid incremental fetching config 1' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                            'query' => '{borough: "Bronx"}',
                            'incrementalFetchingColumn' => 'borough',
                            'incremental' => false,
                            'mapping' =>
                                [
                                    '_id' => null,
                                ],
                    ],
            ],
            'expectedError' => 'Both incremental fetching and query cannot be set together.',
        ];

        yield 'invalid incremental fetching config 2' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                            'sort' => '_id',
                            'incrementalFetchingColumn' => 'borough',
                            'incremental' => false,
                            'mapping' =>
                                [
                                    '_id' => null,
                                ],
                    ],
            ],
            'expectedError' => 'Both incremental fetching and sort cannot be set together.',
        ];

        yield 'invalid authenticationMechanism value (row)' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'host' => '127.0.0.1',
                                'port' => 27017,
                                'database' => 'test',
                                'user' => 'user',
                                'password' => 'password',
                                'authenticationMechanism' => 'INVALID-MECHANISM',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
            'expectedError' => 'The value "INVALID-MECHANISM" is not allowed for path '
                . '"root.parameters.db.authenticationMechanism".',
        ];

        yield 'authenticationMechanism not allowed with custom_uri (row)' => [
            'configData' => [
                'parameters' =>
                    [
                        'db' =>
                            [
                                'protocol' => 'custom_uri',
                                'uri' => 'mongodb://user@localhost/db',
                                'password' => 'pass',
                                'authenticationMechanism' => 'SCRAM-SHA-256',
                            ],
                            'tableName' => 'bronx-bakeries',
                            'collection' => 'restaurants',
                    ],
            ],
            'expectedError' => 'Configuration node "db.authenticationMechanism" '
                . 'is not compatible with custom URI.',
        ];
    }

    /**
     * @param array<string, mixed> $configData
     * @return array<string, mixed>
     */
    protected function addDefaultValues(array $configData, bool $oldConfig = false): array
    {
        if (!array_key_exists('protocol', $configData['parameters']['db'])) {
            $configData['parameters']['db']['protocol'] = DbNode::PROTOCOL_MONGO_DB;
        }

        if (array_key_exists('authenticationMechanism', $configData['parameters']['db'])
            && trim((string) $configData['parameters']['db']['authenticationMechanism']) === ''
        ) {
            unset($configData['parameters']['db']['authenticationMechanism']);
        }

        if ($oldConfig) {
            foreach ($configData['parameters']['exports'] as $key => $value) {
                if (!array_key_exists('mode', $value)) {
                    $configData['parameters']['exports'][$key]['mode'] = 'mapping';
                }

                if (!array_key_exists('includeParentInPK', $value)) {
                    $configData['parameters']['exports'][$key]['includeParentInPK'] = false;
                }

                if (!array_key_exists('enabled', $value)) {
                    $configData['parameters']['exports'][$key]['enabled'] = true;
                }
            }
        } else {
            if (!array_key_exists('mode', $configData['parameters'])) {
                $configData['parameters']['mode'] = 'mapping';
            }

            if (!array_key_exists('includeParentInPK', $configData['parameters'])) {
                $configData['parameters']['includeParentInPK'] = false;
            }
        }

        if (!array_key_exists('quiet', $configData['parameters'])) {
            $configData['parameters']['quiet'] = false;
        }

        return $configData;
    }
}
