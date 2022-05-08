<?php

declare(strict_types = 1);

namespace Keboola\MongoDbExtractor\Tests;

use Generator;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ConfigDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinitionTest extends TestCase
{
    /**
     * @param array<string, mixed> $configData
     * @dataProvider validConfigsData
     */
    public function testValidConfig(array $configData): void
    {
        $config = new Config($configData, new ConfigDefinition());
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
            new Config($configData, new ConfigDefinition());
            $this->fail('Validation should produce error');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString($expectedError, $e->getMessage());
        }
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
    public function invalidConfigsData(): Generator
    {
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
    }

    /**
     * @param array<string, mixed> $configData
     * @return array<string, mixed>
     */
    protected function addDefaultValues(array $configData): array
    {
        if (!array_key_exists('protocol', $configData['parameters']['db'])) {
            $configData['parameters']['db']['protocol'] = ConfigDefinition::PROTOCOL_MONGO_DB;
        }

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

        if (!array_key_exists('quiet', $configData['parameters'])) {
            $configData['parameters']['quiet'] = false;
        }

        return $configData;
    }
}
