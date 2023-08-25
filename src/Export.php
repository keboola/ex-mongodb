<?php

declare(strict_types=1);

namespace MongoExtractor;

use Generator;
use Keboola\Component\UserException;
use MongoExtractor\Config\ExportOptions;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\CallableRetryPolicy;
use Retry\Policy\RetryPolicyInterface;
use Retry\RetryProxy;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Throwable;

class Export
{
    private string $name;
    private JsonDecode $jsonDecoder;
    private RetryProxy $retryProxy;
    private UriFactory $uriFactory;

    /**
     * @param array<string, mixed> $connectionOptions
     */
    public function __construct(
        private ExportCommandFactory $exportCommandFactory,
        private array $connectionOptions,
        private ExportOptions $exportOptions,
        private LoggerInterface $logger
    ) {
        $this->name = Strings::webalize($exportOptions->getName());
        $this->retryProxy = new RetryProxy($this->getRetryPolicy(), new ExponentialBackOffPolicy());
        $this->jsonDecoder = new JsonDecode;
        $this->uriFactory = new UriFactory();
    }

    /**
     * Runs export command
     * @throws \Keboola\Component\UserException
     * @throws \Throwable
     */
    public function export(): Generator
    {
        $options = array_merge($this->connectionOptions, $this->exportOptions->toArray());
        $cliCommand = $this->exportCommandFactory->create($options);
        $process = Process::fromShellCommandline($cliCommand, null, null, null, null);

        $this->retryProxy->call(function () use ($process): void {
            $process->start();
        });

        $this->logger->info(sprintf(
            'Connected to %s',
            $this->uriFactory->create($options)->getConnectionString()
        ));
        $this->logger->info(sprintf('Exporting "%s"', $this->name));

        yield $this->decodeJsonFromOutput($process->getIterator(Process::ITER_SKIP_ERR));

        if (!$process->isSuccessful()) {
            $this->handleMongoExportFails(new ProcessFailedException($process));
        }
    }

    protected function decodeJsonFromOutput(Generator $outputIterator): Generator
    {
        $buffer = '';

        foreach ($outputIterator as $output) {
            $buffer .= $output;
            $lines = preg_split("/((\r?\n)|(\r\n?))/", $buffer);

            if ($lines !== false) {
                for ($i = 0, $count = count($lines) - 1; $i < $count; ++$i) {
                    $line = $lines[$i];
                    if (trim($line) !== '') {
                        yield from $this->processJsonLine($line);
                    }
                }

                $buffer = $lines[$count];
            }
        }

        if (trim($buffer) !== '') {
            yield from $this->processJsonLine($buffer);
        }
    }

    private function processJsonLine(string $line): Generator
    {
        try {
            yield [$this->jsonDecoder->decode($line, JsonEncoder::FORMAT)];
        } catch (NotEncodableValueException $e) {
            $this->logger->warning(sprintf(
                'Could not decode JSON: %s...',
                substr($line, 0, 80)
            ));
        }
    }

    /**
     * @throws \Keboola\Component\UserException
     * @throws \Throwable
     */
    protected function handleMongoExportFails(Throwable $e): void
    {
        if (str_contains($e->getMessage(), 'Failed: EOF')) {
            throw new UserException(sprintf(
                'Export "%s" failed. Timeout occurred while waiting for data. ' .
                'Please check your query. Problem can be a typo in the field name or missing index.' .
                'In these cases, the full scan is made and it can take too long.',
                $this->name
            ));
        }

        if (str_contains($e->getMessage(), 'QueryExceededMemoryLimitNoDiskUseAllowed')) {
            throw new UserException('Sort exceeded memory limit, but did not opt in to ' .
                'external sorting. The field should be set as an index, so there will be no sorting in the ' .
                'incremental fetching query, because the index will be used');
        }

        if (str_contains($e->getMessage(), 'dial tcp: i/o timeout')) {
            throw new UserException('Could not connect to server: connection() error occurred during ' .
                'connection handshake: dial tcp: i/o timeout');
        }

        if (str_contains($e->getMessage(), 'sort key ordering must be 1 (for ascending) or -1 (for descending)')) {
            throw new UserException('$sort key ordering must be 1 (for ascending) or -1 (for descending)');
        }

        if (str_contains($e->getMessage(), 'FieldPath field names may not start with \'$\'')) {
            throw new UserException('FieldPath field names may not start with \'$\'');
        }

        if (preg_match('/(Failed:.*?command)/s', $e->getMessage(), $matches)) {
            if (isset($matches[1])) {
                throw new UserException(trim($matches[1]));
            }
        }

        if (preg_match('/query \'\\[[^\\]]*\\]\' is not valid JSON/i', $e->getMessage())) {
            throw new UserException(sprintf(
                'Export "%s" failed. Query "' . $this->exportOptions->getQuery() . '" is not valid JSON',
                $this->name
            ));
        }

        throw $e;
    }

    public static function buildIncrementalFetchingParams(
        ExportOptions $exportOptions,
        string|int|float|null $inputState
    ): ExportOptions {
        $query = (object) [];
        if (!is_null($inputState)) {
            $query = [
                $exportOptions->getIncrementalFetchingColumn() => [
                    '$gte' => $inputState,
                ],
            ];
        }

        $exportOptions->setQuery(ExportHelper::fixSpecialColumnsInGteQuery(json_encode($query) ?: ''));
        $exportOptions->setSort(json_encode([$exportOptions->getIncrementalFetchingColumn() => 1]) ?: '');

        return $exportOptions;
    }

    /**
     * @throws \Keboola\Component\UserException
     * @throws \Throwable
     */
    public function getLastFetchedValue(): mixed
    {
        // Limit can be disabled with empty string

        $options = array_merge(
            $this->connectionOptions,
            $this->exportOptions->toArray(),
            $this->exportOptions->getLastValueOptions()
        );

        $cliCommand = $this->exportCommandFactory->create($options);
        $process = Process::fromShellCommandline($cliCommand, null, null, null, null);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            $this->handleMongoExportFails($e);
        }

        $output = $process->getOutput();
        if (!empty($output)) {
            // Replace e.g. {"$date":"DATE"} to "ISODate("DATE")"
            $output = ExportHelper::convertSpecialColumnsToString($output);

            $data = $this->jsonDecoder->decode($output, JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE => true]);
            $incrementalFetchingColumn = explode('.', $this->exportOptions->getIncrementalFetchingColumn());
            foreach ($incrementalFetchingColumn as $item) {
                if (!isset($data[$item])) {
                    $fullPathColumnMessage = '';
                    if (count($incrementalFetchingColumn) > 1) {
                        $fullPathColumnMessage = sprintf(
                            ' ("%s")',
                            $this->exportOptions->getIncrementalFetchingColumn()
                        );
                    }
                    throw new UserException(
                        sprintf(
                            'Column "%s"%s does not exists.',
                            $item,
                            $fullPathColumnMessage
                        )
                    );
                }
                $data = $data[$item];
            }

            if (is_array($data)) {
                throw new UserException(sprintf(
                    'Unexpected value "%s" in output of incremental fetching.',
                    json_encode($data)
                ));
            }

            return $data;
        }
        return null;
    }

    private function getRetryPolicy(): RetryPolicyInterface
    {
        return new CallableRetryPolicy(function (Throwable $e) {
            if ($e instanceof UserException) {
                return false;
            }

            return true;
        }, Extractor::RETRY_MAX_ATTEMPTS);
    }
}
