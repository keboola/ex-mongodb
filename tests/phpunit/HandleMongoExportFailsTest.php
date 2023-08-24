<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Unit;

use Generator;
use Keboola\Component\UserException;
use MongoExtractor\Config\ExportOptions;
use MongoExtractor\Export;
use MongoExtractor\ExportCommandFactory;
use MongoExtractor\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class HandleMongoExportFailsTest extends TestCase
{
    /**
     * @dataProvider exceptionsProvider
     * @throws \ReflectionException
     * @throws \Keboola\Component\UserException
     */
    public function testHandleMongoExportFails(
        ProcessFailedException $mongoException,
        UserException $expectedException
    ): void {
        $this->expectException(get_class($expectedException));
        $this->expectExceptionMessage($expectedException->getMessage());

        $class = new ReflectionClass(Export::class);
        $method = $class->getMethod('handleMongoExportFails');
        $exportOptions = new ExportOptions(['name' => '', 'mode' => '']);
        $exportClass = new Export(
            new ExportCommandFactory(new UriFactory(), false),
            [],
            $exportOptions,
            new NullLogger()
        );
        $method->invoke($exportClass, $mongoException);
    }

    public function exceptionsProvider(): Generator
    {
        yield 'dial tcp: i/o timeout' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-01-23T17:02:32.685+0000\t' .
                'could not connect to server: connection() error occured during connection handshake: dial tcp: i/o ' .
                'timeout')),
            new UserException('Could not connect to server: connection() error occurred during ' .
                'connection handshake: dial tcp: i/o timeout'),
        ];

        yield 'QueryExceededMemoryLimitNoDiskUseAllowed' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-01-23T17:02:32.685+0000\t' .
                '(QueryExceededMemoryLimitNoDiskUseAllowed) Sort exceeded memory limit of 104857600 bytes, but did ' .
                'not opt in to external sorting.')),
            new UserException('Sort exceeded memory limit, but did not opt in to ' .
                'external sorting. The field should be set as an index, so there will be no sorting in the ' .
                'incremental fetching query, because the index will be used'),
        ];

        yield 'InvalidSortKey' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-04-04T09:29:12.698+0000\t' .
                'Failed: (Location15975) $sort key ordering must be 1 (for ascending) or -1 (for descending)')),
            new UserException('$sort key ordering must be 1 (for ascending) or -1 (for descending)'),
        ];

        yield 'Unauthorized' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-05-11T00:07:31.097+0000\t' .
                'connected to: mongodb+srv://[**REDACTED**]@cl-shared-all-dev-web.3ebye.mongodb.net/slotManagementDB' .
                'Test 2023-05-11T00:07:31.178+0000\tFailed: (Unauthorized) not authorized on slotManagementDBTest to ' .
                'execute command { aggregate: \"settings\", cursor: {}, pipeline: [ { $collStats: { count: {} } }, { ' .
                '$group: { _id: 1, n: { $sum: \"$count\" } } } ], lsid: { id: UUID(\"7a85274b-06b1-4314-b625-25b0a022' .
                '2454\") }, $clusterTime: { clusterTime: Timestamp(1683763642, 1), signature: { hash: BinData(0, 4F8D' .
                'EAC4648CBF6050C6CC7A397F6A0FAFC277EC), keyId: 7218577901191430149 } }, $db: \"slotManagementDBTest\"' .
                ' $readPreference: { mode: \"primary\" } }')),
            new UserException('Failed: (Unauthorized) not authorized on slotManagementDBTest to ' .
                'execute command'),
        ];

        yield 'field names may not start with $' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-05-17T12:49:22.079+0000\t' .
                'connected to: mongodb+srv://[**REDACTED**]@cl-shared-all-prod-web.x0u5m.mongodb.net/slotManagementDB' .
                '2023-05-17T12:49:22.238+0000\tFailed: (Location16410) FieldPath field names may not start with \'$\'' .
                'Consider using $getField or $setField.')),
            new UserException('FieldPath field names may not start with \'$\''),
        ];
    }

    private function createMockInstanceOfProcess(string $errorOutput): Process
    {
        $mockProcess = $this->createMock(Process::class);
        $mockProcess->method('isSuccessful')->willReturn(false);
        $mockProcess->method('getCommandLine')->willReturn('mongoexport --uri ' .
            '\'mongodb://user:pass@mongo/mongodb\' --collection \'transactions\' ' .
            '--query \'{\"_id\":{\"$gte\":{\"$oid\": \"63ceb66a967f8f0017ceed64\"}}}\' --sort \'{\"_id\":1}\' ' .
            '--type \'json\'');
        $mockProcess->method('getExitCode')->willReturn(1);
        $mockProcess->method('getExitCodeText')->willReturn('General error');
        $mockProcess->method('getWorkingDirectory')->willReturn('/code');
        $mockProcess->method('getOutput')->willReturn('');
        $mockProcess->method('getErrorOutput')->willReturn($errorOutput);

        return $mockProcess;
    }
}
