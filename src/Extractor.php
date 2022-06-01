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
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;

class Extractor
{
    public const RETRY_MAX_ATTEMPTS = 5;

    private RetryProxy $retryProxy;

    /**
     * @param array<mixed, mixed> $inputState
     * @throws \Keboola\Component\UserException
     */
    public function __construct(
        private UriFactory $uriFactory,
        private ExportCommandFactory $exportCommandFactory,
        private Config $config,
        private array $inputState = []
    ) {
        $simpleRetryPolicy = new SimpleRetryPolicy(self::RETRY_MAX_ATTEMPTS);
        $this->retryProxy = new RetryProxy($simpleRetryPolicy, new ExponentialBackOffPolicy());

        if ($config->isSshEnabled()) {
            $sshOptions = $this->config->getSshOptions();
            $sshKeys = $this->config->getSshKeys();
            $sshOptions['privateKey'] = $sshKeys['#private'] ?? $sshKeys['private'];
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

        $this->retryProxy->call(function () use ($manager, $uri): void {
            try {
                $manager->executeCommand($uri->getDatabase(), new Command(['listCollections' => 1]));
            } catch (Exception $exception) {
                echo sprintf('Retrying (%sx)...%s', $this->retryProxy->getTryCount(), PHP_EOL);
                throw new UserException($exception->getMessage(), 0, $exception);
            }
        });
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
        $lastFetchedValue = null;
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
     * @param array<string, string|int|bool|array<string,string>> $sshOptions
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

    /**
     * @param array<int, array{path: string, primaryKey: array<int, string>|string|null}> $manifestsData
     */
    protected function generateManifests(array $manifestsData, ExportOptions $exportOptions): void
    {
        foreach ($manifestsData as $manifestData) {
            (new Manifest($exportOptions, $manifestData['path'], $manifestData['primaryKey']))->generate();
        }
    }
}
