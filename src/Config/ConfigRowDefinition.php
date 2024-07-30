<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigRowDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        $parametersNode->validate()->always(function (array $v) {
            if (isset($v['query'], $v['incrementalFetchingColumn']) && $v['query'] !== '') {
                throw new InvalidConfigurationException(
                    'Both incremental fetching and query cannot be set together.',
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
        });

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->append(new DbNode())
                ->booleanNode('quiet')
                    ->defaultFalse()
                ->end()
                ->scalarNode('tableName')
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
                    ->values([ExportOptions::MODE_MAPPING, ExportOptions::MODE_RAW])
                    ->defaultValue(ExportOptions::MODE_MAPPING)
                ->end()
                ->booleanNode('includeParentInPK')
                    ->defaultValue(false)
                ->end()
                ->booleanNode('incremental')->end()
                ->variableNode('mapping')
                ->end()
            ->end()
        ;

        // @formatter:on
        return $parametersNode;
    }
}
