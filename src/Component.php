<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\BaseComponent;
use Keboola\Component\JsonHelper;
use Keboola\Component\UserException;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ConfigRowDefinition;
use MongoExtractor\Config\OldConfigDefinition;
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
        if ($this->getConfigDefinitionClass() === OldConfigDefinition::class
            && $this->areExportsDuplicated($parameters['exports'])) {
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

    /**
     * @throws \Keboola\Component\UserException
     */
    protected function getConfigDefinitionClass(): string
    {
        $rawConfig = $this->getRawConfig();
        if (empty($rawConfig) || empty($rawConfig['parameters'])) {
            throw new UserException('Missing config');
        }

        if (array_key_exists('exports', $rawConfig['parameters'])) {
            return OldConfigDefinition::class;
        }

        return ConfigRowDefinition::class;
    }

    protected function getRawConfig(): array
    {
        return JsonHelper::readFile(sprintf("%s/config.json", $this->getDataDir()));
    }

    /**
     * @param array<int, mixed> $exports
     */
    protected function areExportsDuplicated(array $exports): bool
    {
        return count($exports) !== count(array_unique(array_column($exports, 'name')));
    }
}
