<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinition extends BaseConfigDefinition
{
    public const PROTOCOL_MONGO_DB = 'mongodb';

    // https://docs.mongodb.com/manual/reference/connection-string/#dns-seedlist-connection-format
    public const PROTOCOL_MONGO_DB_SRV = 'mongodb+srv';

    public const PROTOCOL_CUSTOM_URI = 'custom_uri';

    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->arrayNode('db')
                    ->validate()
                        ->always(function (array $v) {
                            $protocol = $v['protocol'];
                            $sshTunnelEnabled = $v['ssh']['enabled'] ?? false;
                            $v['password'] = $v['password'] ?? $v['#password'] ?? null;

                            if ($protocol === self::PROTOCOL_CUSTOM_URI) {
                                // Validation for "custom_uri" protocol
                                if (!isset($v['uri'])) {
                                    throw new InvalidConfigurationException(
                                        'The child node "uri" at path "parameters.db" must be configured.'
                                    );
                                }

                                // SSH tunnel cannot be used with custom URI
                                if ($sshTunnelEnabled) {
                                    throw new InvalidConfigurationException(
                                        'Custom URI is not compatible with SSH tunnel support.'
                                    );
                                }

                                // Check incompatible keys
                                foreach (['host', 'port', 'database', 'authenticationDatabase'] as $key) {
                                    if (isset($v[$key])) {
                                        throw new InvalidConfigurationException(sprintf(
                                            'Configuration node "db.%s" is not compatible with custom URI.',
                                            $key
                                        ));
                                    }
                                }
                            } else {
                                // Validation for "mongodb" or "mongodb+srv" protocol
                                if (!isset($v['host'])) {
                                    throw new InvalidConfigurationException(
                                        'The child node "host" at path "parameters.db" must be configured.'
                                    );
                                }

                                if (!isset($v['database'])) {
                                    throw new InvalidConfigurationException(
                                        'The child node "database" at path "parameters.db" must be configured.'
                                    );
                                }

                                // Validate auth options: both or none
                                if (isset($v['user']) xor isset($v['password'])) {
                                    throw new InvalidConfigurationException(
                                        'When passing authentication details,' .
                                        ' both "user" and "password" params are required'
                                    );
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->enumNode('protocol')
                            ->values([self::PROTOCOL_MONGO_DB, self::PROTOCOL_MONGO_DB_SRV, self::PROTOCOL_CUSTOM_URI])
                            ->defaultValue(self::PROTOCOL_MONGO_DB)
                        ->end()
                        ->scalarNode('uri')->cannotBeEmpty()->end()
                        ->scalarNode('host')->cannotBeEmpty()->end()
                        ->scalarNode('port')->cannotBeEmpty()->end()
                        ->scalarNode('database')->cannotBeEmpty()->end()
                        ->scalarNode('authenticationDatabase')->end()
                        ->scalarNode('user')->end()
                        ->scalarNode('password')->end()
                        ->scalarNode('#password')->end()
                        ->append($this->addSshNode())
                    ->end()
                ->end()
                ->booleanNode('quiet')
                    ->defaultFalse()
                ->end()
                ->arrayNode('exports')
                    ->prototype('array')
                        ->validate()
                        ->always(function ($v) {
                            if (isset($v['query'], $v['incrementalFetchingColumn']) && $v['query'] !== '') {
                                throw new InvalidConfigurationException(
                                    'Both incremental fetching and query cannot be set together.'
                                );
                            }
                            if (isset($v['sort'], $v['incrementalFetchingColumn']) && $v['sort'] !== '') {
                                $message = 'Both incremental fetching and sort cannot be set together.';
                                throw new InvalidConfigurationException($message);
                            }

                            // Normalize incrementalFetchingColumn:
                            // In mapping are dates exported as "PARENT.FIELD.$date",
                            // ... but for incremental fetching is needed to enter "PARENT.FIELD"
                            // Therefore, the user would not be confused,
                            // we support both variants: "PARENT.FIELD.$date" and "PARENT.FIELD"
                            if (isset($v['incrementalFetchingColumn'])) {
                                $v['incrementalFetchingColumn'] =
                                    preg_replace('~\.\$date$~', '', $v['incrementalFetchingColumn']);
                            }

                            return $v;
                        })
                        ->end()
                        ->children()
                            ->scalarNode('id')->end()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('collection')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('query')->end()
                            ->scalarNode('incrementalFetchingColumn')->end()
                            ->scalarNode('sort')->end()
                            ->scalarNode('limit')->end()
                            ->enumNode('mode')
                                ->values(['mapping', 'raw'])
                                ->defaultValue('mapping')
                            ->end()
                            ->booleanNode('includeParentInPK')
                                ->defaultValue(false)
                            ->end()
                            ->booleanNode('enabled')
                                ->defaultValue(true)
                            ->end()
                            ->booleanNode('incremental')->end()
                            ->variableNode('mapping')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        // @formatter:on
        return $parametersNode;
    }

     private function addSshNode(): ArrayNodeDefinition
    {
        $sshNode = new ArrayNodeDefinition('ssh');
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $sshNode
            ->children()
                ->booleanNode('enabled')->end()
                ->arrayNode('keys')
                    ->children()
                        ->scalarNode('private')->end()
                        ->scalarNode('#private')->end()
                        ->scalarNode('public')->end()
                    ->end()
                ->end()
                ->scalarNode('sshHost')->end()
                ->scalarNode('sshPort')->end()
                ->scalarNode('remoteHost')->end()
                ->scalarNode('remotePort')->end()
                ->scalarNode('localPort')->end()
                ->scalarNode('user')->end()
            ->end()
        ;

        return $sshNode;
    }
}
