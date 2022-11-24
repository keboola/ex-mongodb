<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\UserException;
use Keboola\SSHTunnel\SSH;
use Keboola\SSHTunnel\SSHException;
use Keboola\Temp\Temp;
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

    /** @var mixed[] */
    private array $dbParams;

    private static function createSSLFile(Temp $temp, string $fileContent): string
    {
        $filename = $temp->createTmpFile('ssl');
        file_put_contents((string) $filename, $fileContent);
        return (string) $filename->getRealPath();
    }

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
            $this->createSshTunnel($this->config->getSshOptions());
        }

        $this->writeSslFiles();
    }

    /**
     * Sends listCollections command to test connection/credentials
     * @throws \Keboola\Component\UserException
     */
    public function testConnection(): void
    {
        $uri = $this->uriFactory->create($this->dbParams);
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
            $id = $exportOptions->getId();
            if ($hasIncrementalFetchingColumn) {
                if ($this->config->isOldConfig()) {
                    $lastFetchedValue = $this->inputState['lastFetchedRow'][$id] ?? null;
                } else {
                    $lastFetchedValue = $this->inputState['lastFetchedRow'] ?? null;
                }
                $exportOptions = Export::buildIncrementalFetchingParams($exportOptions, $lastFetchedValue);
            }

            $export = new Export($this->exportCommandFactory, $this->dbParams, $exportOptions);
            if ($exportOptions->isEnabled()) {
                $count++;
                if ($hasIncrementalFetchingColumn) {
                    if ($this->config->isOldConfig()) {
                        $lastFetchedValues[$id] = $export->getLastFetchedValue() ?? $lastFetchedValue;
                    } else {
                        $lastFetchedValues = $export->getLastFetchedValue() ?? $lastFetchedValue;
                    }
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
     * @param array<string, array{path: string, primaryKey: array<int, string>|string|null}> $manifestsData
     */
    protected function generateManifests(array $manifestsData, ExportOptions $exportOptions): void
    {
        foreach ($manifestsData as $manifestData) {
            (new Manifest($exportOptions, $manifestData['path'], $manifestData['primaryKey']))->generate();
        }
    }

    protected function writeSslFiles(): void
    {
        $this->dbParams = $this->config->getDb();
        if (($this->dbParams['ssl']['enabled'] ?? false)) {
            $ssl = $this->dbParams['ssl'];
            $temp = new Temp('mongodb-ssl');
            if (isset($ssl['ca'])) {
                $this->dbParams['ssl']['caFile'] = self::createSSLFile($temp, $ssl['ca']);
            }
            if (isset($ssl['cert']) && isset($ssl['#key'])) {
                $this->dbParams['ssl']['certKeyFile'] = self::createSSLFile($temp, $ssl['cert'] . "\n" . $ssl['#key']);
            }
        }
    }
}
