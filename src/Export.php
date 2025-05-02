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
        private LoggerInterface $logger,
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
            $this->uriFactory->create($options)->getConnectionString(),
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
                substr($line, 0, 80),
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
                $this->name,
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
                $this->name,
            ));
        }

        throw $e;
    }

    public static function buildIncrementalFetchingParams(
        ExportOptions $exportOptions,
        string|int|float|null $inputState,
    ): ExportOptions {
        $query = (object) [];
        if (!is_null($inputState)) {
            $operator = $exportOptions->useIncrementalGreaterOperator() ? '$gt' : '$gte';
            $query = [
                $exportOptions->getIncrementalFetchingColumn() => [
                    $operator => $inputState,
                ],
            ];
        }

        $fixedQuery = self::fixSpecialColumnsInQuery(json_encode($query) ?: '', $exportOptions->getQuery());
        $exportOptions->setQuery($fixedQuery);
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
        $lastValueOptions = [
            $this->exportOptions->getLastValueOptions(),
            [
                'limit' => 1,
                'sort' => json_encode([$this->exportOptions->getIncrementalFetchingColumn() => -1]),
            ],
        ];
        foreach ($lastValueOptions as $lastValueOption) {
            $options = array_merge(
                $this->connectionOptions,
                $this->exportOptions->toArray(),
                $lastValueOption,
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
                break;
            }
        }

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
                            $this->exportOptions->getIncrementalFetchingColumn(),
                        );
                    }
                    throw new UserException(
                        sprintf(
                            'Column "%s"%s does not exists.',
                            $item,
                            $fullPathColumnMessage,
                        ),
                    );
                }
                $data = $data[$item];
            }

            if (is_array($data)) {
                throw new UserException(sprintf(
                    'Unexpected value "%s" in output of incremental fetching.',
                    json_encode($data),
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

    /**
     * This function fix invalid type in $gte query, specific for incremental fetching
     *
     * Example:
     * User define incremental fetching column "id" (numeric) and last state value is "123".
     * Query `{"id":{"$gte":123}}` will be generated correctly.
     *
     * User define incremental fetching column "sub.id" (numeric), but sub document not exists in last row.
     * Query `{"sub.id":{"$gte":null}}` will be generated, but `null` value will be converted to `""` (empty string).
     * This is invalid query, because `mongoexport` expects number, not string.
     * So, we need load original query from config and use it to fix types.
     *
     * @param string $query Generated query by incremental fetching.
     * @param string|null $originalQuery Original query defined in config.
     * @return string Fixed query string.
     */
    public static function fixSpecialColumnsInQuery(string $query, ?string $originalQuery): string
    {
        // Replace eg. {"updated":{"$gte":{"$date":"2020-11-20T13:37:04+00:00"}}}
        // to {"updated":{"$gte":ISODate("2020-11-20T13:37:04+00:00")}}.
        // Note: ISODate is not valid Extended JSON v2, we expect that mongoexport handle it.
        $query = ExportHelper::convertSpecialColumnsToString($query);

        // Fix types based on original query
        if ($originalQuery) {
            $query = self::fixTypesInQueryRecursive(json_decode($query, true), json_decode($originalQuery, true));
            $query = json_encode($query) ?: '';
        }

        return $query;
    }

    private static function fixTypesInQueryRecursive(mixed $queryData, mixed $originalQueryData): mixed
    {
        if (!is_array($queryData) || !is_array($originalQueryData)) {
            return $queryData; // Cannot determine type from original query, use query data as is
        }

        foreach ($queryData as $key => $value) {
            if (!isset($originalQueryData[$key])) {
                continue; // Key not present in original query, skip
            }

            $originalValue = $originalQueryData[$key];
            if (is_array($value) && is_array($originalValue)) {
                // Recursively fix types in nested arrays/objects
                $queryData[$key] = self::fixTypesInQueryRecursive($value, $originalValue);
            } elseif (gettype($value) !== gettype($originalValue) && !is_array($value) && !is_array($originalValue)) {
                // Fix type if types mismatch and values are scalar
                settype($queryData[$key], gettype($originalValue));
            }
        }

        return $queryData;
    }
}
