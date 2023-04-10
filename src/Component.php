<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\Temp\Temp;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ConfigRowDefinition;
use MongoExtractor\Config\ExportOptions;
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

        $config = $this->getConfig();
        if ($this->getConfigDefinitionClass() === OldConfigDefinition::class
            && $this->areExportsDuplicated($config->getExportOptions())) {
            throw new UserException('Please remove duplicate export names');
        }

        $uriFactory = new UriFactory();
        $exportCommandFactory = new ExportCommandFactory($uriFactory, $config->isQuietModeEnabled());
        $this->extractor = new Extractor(
            $uriFactory,
            $exportCommandFactory,
            $config,
            $this->getLogger(),
            $this->getInputState()
        );
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    protected function run(): void
    {
        try {
            $this->extractor->extract($this->getDataDir() . '/out/tables');
        } finally {
            (new Temp())->remove();
        }
    }

    /**
     * Tests connection
     * @return array<string, string>
     * @throws \Keboola\Component\UserException
     */
    public function testConnection(): array
    {
        try {
            $this->extractor->testConnection();
        } finally {
            (new Temp())->remove();
        }
        return [
            'status' => 'ok',
        ];
    }

    /**
     * @return array<string, string>
     */
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

    public function getConfig(): Config
    {
        /** @var Config $config */
        $config = parent::getConfig();
        return $config;
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

    /**
     * @param ExportOptions[] $exports
     */
    protected function areExportsDuplicated(array $exports): bool
    {
        $exportNames = array_map(fn(ExportOptions $export): string => $export->getName(), $exports);

        return count($exports) !== count(array_unique($exportNames));
    }
}
