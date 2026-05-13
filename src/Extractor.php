<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\UserException;
use Keboola\SSHTunnel\SSH;
use Keboola\SSHTunnel\SSHException;
use Keboola\Temp\Temp;
use MongoExtractor\Config\Config;
use MongoExtractor\Config\ExportOptions;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Symfony\Component\Process\Process;

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
        private LoggerInterface $logger,
        private array $inputState = [],
    ) {
        $simpleRetryPolicy = new SimpleRetryPolicy(self::RETRY_MAX_ATTEMPTS);
        $this->retryProxy = new RetryProxy($simpleRetryPolicy, new ExponentialBackOffPolicy());

        if ($config->isSshEnabled()) {
            $this->createSshTunnel($this->config->getSshOptions());
        }

        $this->writeSslFiles();
    }

    /**
     * connectTimeoutMS bounds the underlying TCP connect (slow VPN routes hung
     * here previously). serverSelectionTimeoutMS bounds post-handshake driver
     * selection. The Process wall-clock must comfortably exceed both so mongosh
     * can emit a clean error instead of being SIGKILLed.
     */
    private const MONGOSH_CONNECT_TIMEOUT_MS = 10000;
    private const MONGOSH_SERVER_SELECTION_TIMEOUT_MS = 5000;
    private const MONGOSH_PROCESS_TIMEOUT_SECONDS = 60;

    /**
     * Tests connection using mongosh CLI
     * @throws \Keboola\Component\UserException
     */
    public function testConnection(): void
    {
        $uri = $this->uriFactory->create($this->dbParams);
        $uriString = self::appendMongoshTimeouts($uri);

        $command = ['mongosh', $uriString, '--eval', 'db.runCommand({listCollections: 1})', '--quiet', '--norc'];

        // Add TLS cert flags when SSL is enabled and cert files are provided
        if (($this->dbParams['ssl']['enabled'] ?? false)) {
            if (isset($this->dbParams['ssl']['caFile'])) {
                $command[] = '--tlsCAFile=' . $this->dbParams['ssl']['caFile'];
            }
            if (isset($this->dbParams['ssl']['certKeyFile'])) {
                $command[] = '--tlsCertificateKeyFile=' . $this->dbParams['ssl']['certKeyFile'];
            }
        }

        $this->retryProxy->call(function () use ($command): void {
            $process = new Process($command);
            $process->setTimeout(self::MONGOSH_PROCESS_TIMEOUT_SECONDS);
            $process->run();

            if (!$process->isSuccessful()) {
                echo sprintf('Retrying (%sx)...%s', $this->retryProxy->getTryCount(), PHP_EOL);
                $errorMessage = self::parseMongoshError(
                    $process->getErrorOutput(),
                    $process->getOutput(),
                );
                throw new UserException($errorMessage);
            }
        });
    }

    /**
     * Appends connectTimeoutMS and serverSelectionTimeoutMS to a mongosh URI so
     * a connect failure surfaces as a clean MongoNetworkError instead of being
     * killed by the wrapping Process timeout. User-supplied values take
     * precedence — caller-provided timeouts are never overridden.
     */
    public static function appendMongoshTimeouts(Uri $uri): string
    {
        $defaults = [
            'connectTimeoutMS' => self::MONGOSH_CONNECT_TIMEOUT_MS,
            'serverSelectionTimeoutMS' => self::MONGOSH_SERVER_SELECTION_TIMEOUT_MS,
        ];

        $uriString = (string) $uri;
        $query = $uri->getQuery();

        foreach ($defaults as $key => $value) {
            if (!$query->has($key)) {
                $separator = str_contains($uriString, '?') ? '&' : '?';
                $uriString .= $separator . $key . '=' . $value;
            }
        }

        return $uriString;
    }

    /**
     * Extracts a meaningful error message from mongosh output.
     * Strips the Mongo*Error class prefix and maps technical errors to user-friendly messages.
     */
    public static function parseMongoshError(string $stderr, string $stdout): string
    {
        $output = trim($stderr) !== '' ? trim($stderr) : trim($stdout);

        if ($output === '') {
            return 'Connection test failed';
        }

        // mongosh errors look like: "MongoServerSelectionError: message"
        // Extract just the message part for cleaner user output
        $message = $output;
        if (preg_match('/^Mongo\w+Error:\s*(.+)$/m', $output, $matches)) {
            $message = trim($matches[1]);
        } else {
            // Use first non-empty line as fallback
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $message = $line;
                    break;
                }
            }
        }

        return self::mapMongoshErrorToUserMessage($message);
    }

    /**
     * Maps technical mongosh error messages to user-friendly messages.
     */
    private static function mapMongoshErrorToUserMessage(string $message): string
    {
        // DNS resolution failure: "getaddrinfo ENOTFOUND host" or "getaddrinfo EAI_AGAIN host"
        if (preg_match('/getaddrinfo \w+\s+(\S+)/', $message, $matches)) {
            return sprintf("Could not resolve hostname '%s'. Please check the host configuration.", $matches[1]);
        }

        // Connection refused: "connect ECONNREFUSED 127.0.0.1:27017"
        if (preg_match('/connect ECONNREFUSED\s+(\S+)/', $message, $matches)) {
            return sprintf('Connection refused to %s. Please check the host and port configuration.', $matches[1]);
        }

        // Connection timeout: "connection timed out" or "Server selection timed out"
        if (str_contains(strtolower($message), 'timed out')) {
            return 'Connection timed out. Please check the host and port configuration.';
        }

        // Malformed URI / unescaped characters — mongosh misinterprets bad URIs
        if (str_contains($message, 'unescaped characters')) {
            return 'Failed to parse connection URI. Please check the connection parameters.';
        }

        return $message;
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

            $export = new Export($this->exportCommandFactory, $this->dbParams, $exportOptions, $this->logger);
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
     * @param array<string, array{
     *     path: string,
     *     primaryKey: array<int, string>,
     *     columns: array<int, string>
     * }> $manifestsData
     */
    protected function generateManifests(array $manifestsData, ExportOptions $exportOptions): void
    {
        foreach ($manifestsData as $manifestData) {
            (new Manifest(
                $this->config->getDataTypeSupport(),
                $exportOptions->isIncrementalFetching(),
                $manifestData['path'],
                $manifestData['primaryKey'],
                $manifestData['columns'],
            )
            )->generate();
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
