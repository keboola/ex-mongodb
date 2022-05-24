<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\UserException;
use Keboola\SSHTunnel\SSH;
use Keboola\SSHTunnel\SSHException;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\Exception;
use MongoDB\Driver\Manager;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ExportOptions;

class Extractor
{

    /**
     * @throws \Keboola\Component\UserException
     */
    public function __construct(
        private UriFactory $uriFactory,
        private ExportCommandFactory $exportCommandFactory,
        private Config $config,
        private array $inputState = []
    ) {
        if ($config->isSshEnabled()) {
            $sshOptions = $this->config->getSshOptions();
            $sshOptions['privateKey'] = $sshOptions['keys']['#private'] ?? $sshOptions['keys']['private'];
            $sshOptions['sshPort'] = 22;

            $this->createSshTunnel($sshOptions);
        }
    }

    /**
     * Sends listCollections command to test connection/credentials
     * @throws \Keboola\Component\UserException
     */
    public function testConnection(): void
    {
        $uri = $this->uriFactory->create($this->config->getDb());
        try {
            $manager = new Manager((string) $uri);
        } catch (Exception $exception) {
            throw new UserException($exception->getMessage(), 0, $exception);
        }

        try {
            $manager->executeCommand($uri->getDatabase(), new Command(['listCollections' => 1]));
        } catch (Exception $exception) {
            throw new UserException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Creates exports and runs extraction
     * @throws \Exception
     * @throws \Throwable
     */
    public function extract(string $outputPath): void
    {
        $this->testConnection();

        $count = 0;
        $lastFetchedValues = [];
        foreach ($this->config->getExportOptions() as $exportOptions) {
            $hasIncrementalFetchingColumn = $exportOptions->hasIncrementalFetchingColumn();
            if ($hasIncrementalFetchingColumn) {
                $lastFetchedValue = $this->inputState['lastFetchedRow'][$exportOptions->getId()] ?? null;
                $exportOptions = Export::buildIncrementalFetchingParams($exportOptions, $lastFetchedValue);
            }

            $export = new Export($this->exportCommandFactory, $this->config->getDb(), $exportOptions);
            if ($exportOptions->isEnabled()) {
                $count++;
                if ($hasIncrementalFetchingColumn) {
                    $lastFetchedValues[$exportOptions->getId()] = $export->getLastFetchedValue() ?? $lastFetchedValue;
                }
                $manifestData = (new Parse($exportOptions, $outputPath))->parse($export->export());
                $this->generateManifests($manifestData, $exportOptions);
            }
        }

        if (!empty($lastFetchedValues)) {
            Parse::saveStateFile($outputPath, $lastFetchedValues);
        }

        if ($count === 0) {
            throw new UserException('Please enable at least one export');
        }
    }

    /**
     * @throws \Keboola\Component\UserException
     */
    private function createSshTunnel(array $sshOptions): void
    {
        try {
            (new SSH())->openTunnel($sshOptions);
        } catch (SSHException $e) {
            throw new UserException($e->getMessage());
        }
    }

    protected function generateManifests(array $manifestsData, ExportOptions $exportOptions): void
    {
        foreach ($manifestsData as $manifestData) {
            (new Manifest($exportOptions, $manifestData['path'], $manifestData['primaryKey']))->generate();
        }
    }
}
