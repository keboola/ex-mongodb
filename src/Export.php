<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\UserException;
use MongoExtractor\Config\ExportOptions;
use Nette\Utils\Strings;
use PhpParser\JsonDecoder;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\CallableRetryPolicy;
use Retry\Policy\RetryPolicyInterface;
use Retry\RetryProxy;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class Export
{
    private string $name;
    private ConsoleOutput $consoleOutput;
    private JsonDecoder $jsonDecoder;
    private RetryProxy $retryProxy;

    /**
     * @param array<string, mixed> $connectionOptions
     */
    public function __construct(
        private ExportCommandFactory $exportCommandFactory,
        private array $connectionOptions,
        private ExportOptions $exportOptions,
    ) {
        $this->name = Strings::webalize($exportOptions->getName());
        $this->retryProxy = new RetryProxy($this->getRetryPolicy(), new ExponentialBackOffPolicy());
        $this->consoleOutput = new ConsoleOutput;
        $this->jsonDecoder = new JsonDecoder();
    }

    /**
     * Runs export command
     * @return array<int, string>
     * @throws \Keboola\Component\UserException
     * @throws \Throwable
     */
    public function export(): array
    {
        $options = array_merge($this->connectionOptions, $this->exportOptions->toArray());
        $cliCommand = $this->exportCommandFactory->create($options);
        $process = Process::fromShellCommandline($cliCommand, null, null, null, null);

        $this->retryProxy->call(function () use ($process): void {
            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                $this->handleMongoExportFails($e);
            }
        });

        $exportedRows = $this->getExportedRowsAsArrayFromOutput($process->getOutput());

        $recordsCount = count($exportedRows);
        $this->consoleOutput->writeln(sprintf(
            'Exported %d %s',
            $recordsCount,
            $recordsCount === 1 ? 'record' : 'records'
        ));

        return $exportedRows;
    }

    /**
     * @return array<int, string>
     */
    protected function getExportedRowsAsArrayFromOutput(string $output): array
    {
        $exportedRows = preg_split("/((\r?\n)|(\r\n?))/", $output);

        return $exportedRows ? array_filter($exportedRows) : [];
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
        $process->mustRun();

        $output = $process->getOutput();
        if (!empty($output)) {
            // Replace e.g. {"$date":"DATE"} to "ISODate("DATE")"
            $output = ExportHelper::convertSpecialColumnsToString($output);

            $data = $this->jsonDecoder->decode($output);
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
