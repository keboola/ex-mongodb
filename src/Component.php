<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ConfigDefinition;
use Psr\Log\LoggerInterface;

class Component extends BaseComponent
{
    private const ACTION_TEST_CONNECTION = 'testConnection';
    private Extractor $extractor;

    /**
     * @throws \Keboola\Component\UserException
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        $parameters = $this->getConfig()->getParameters();
        if (empty($parameters)) {
            throw new UserException('Missing config');
        }

        if (count($parameters['exports'])
            !== count(array_unique(array_column($parameters['exports'], 'name')))) {
            throw new UserException('Please remove duplicate export names');
        }

        $uriFactory = new UriFactory();
        $exportCommandFactory = new ExportCommandFactory($uriFactory, $parameters['quiet']);
        $this->extractor = new Extractor($uriFactory, $exportCommandFactory, $parameters, $this->getInputState());
    }

    /**
     * @throws \Exception
     */
    protected function run(): void
    {
        $this->extractor->extract($this->getDataDir() . '/out/tables');
    }

    /**
     * Tests connection
     * @return array<string, string>
     * @throws \Keboola\Component\UserException
     */
    public function testConnection(): array
    {
        $this->extractor->testConnection();
        return [
            'status' => 'ok',
        ];
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_TEST_CONNECTION => 'testConnection',
        ];
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
