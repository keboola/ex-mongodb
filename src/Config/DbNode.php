<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class DbNode extends ArrayNodeDefinition
{
    public const PROTOCOL_MONGO_DB = 'mongodb';

    // https://docs.mongodb.com/manual/reference/connection-string/#dns-seedlist-connection-format
    public const PROTOCOL_MONGO_DB_SRV = 'mongodb+srv';

    public const PROTOCOL_CUSTOM_URI = 'custom_uri';

    public const NODE_NAME = 'db';

    public function __construct()
    {
        parent::__construct(self::NODE_NAME);
        $this->validate()->always(function (array $v): array {
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
        })->end()->end();
        $this->isRequired();
        $this->init($this->children());
    }

    protected function init(NodeBuilder $builder): void
    {
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $builder
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
        ;

        // @formatter:on
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
