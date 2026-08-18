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
        UserException $expectedException,
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
            new NullLogger(),
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

        yield 'ShutdownInProgress / quiesce mode' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2026-07-20T08:41:54.107+0000\t' .
                'example-db.exampleCollection\t466797 2026-07-20T08:41:54.107+0000\tFailed:  connection pool for ' .
                'shard-00-01.example.mongodb.net:27016 was cleared because another operation failed with: ' .
                '(ShutdownInProgress) Mongos is in quiesce mode and will shut down')),
            new UserException(
                'The MongoDB server is shutting down or restarting (Mongos is in quiesce mode), ' .
                'so the export could not be completed. This is usually a temporary state caused by ' .
                'server maintenance or a failover. Please try running the extraction again in a few minutes.',
            ),
        ];

        yield 'AuthenticationFailed' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2026-07-23T09:02:25.402+0000\t' .
                'failed to connect to mongodb://[**REDACTED**]/production: connection() error occurred during ' .
                'connection handshake: auth error: sasl conversation error: unable to authenticate using ' .
                'mechanism "SCRAM-SHA-1": (AuthenticationFailed) Authentication failed.')),
            new UserException('Could not authenticate against the MongoDB server. ' .
                'Please check the configured username, password and authentication database.'),
        ];

        // The 'dial tcp: i/o timeout' case above uses a shortened message. In production the Go
        // driver puts the host:port between "dial tcp" and "i/o timeout", so that check never
        // matched a real handshake timeout and it fell through as an opaque internal error.
        yield 'connection handshake i/o timeout (real message, host:port in the middle)' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2026-07-30T03:14:11.204+0000\t' .
                'Failed: error connecting to db server: connection() error occurred during connection ' .
                'handshake: dial tcp 10.20.30.40:27017: i/o timeout')),
            new UserException('Could not connect to the MongoDB server: the connection timed out while ' .
                'establishing the session. This is usually a temporary network problem, or the server is ' .
                'unreachable or overloaded. Please check the configured host, port and network access, ' .
                'then try running the extraction again.'),
        ];

        yield 'server selection timeout (context deadline exceeded)' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2026-07-30T03:14:11.204+0000\t' .
                'Failed: error connecting to db server: server selection error: context deadline exceeded, ' .
                'current topology: { Type: ReplicaSetNoPrimary, Servers: [ ... ] }')),
            new UserException('Could not connect to the MongoDB server: the connection timed out while ' .
                'establishing the session. This is usually a temporary network problem, or the server is ' .
                'unreachable or overloaded. Please check the configured host, port and network access, ' .
                'then try running the extraction again.'),
        ];

        yield 'field names may not start with $' => [
            new ProcessFailedException($this->createMockInstanceOfProcess('2023-05-17T12:49:22.079+0000\t' .
                'connected to: mongodb+srv://[**REDACTED**]@cl-shared-all-prod-web.x0u5m.mongodb.net/slotManagementDB' .
                '2023-05-17T12:49:22.238+0000\tFailed: (Location16410) FieldPath field names may not start with \'$\'' .
                'Consider using $getField or $setField.')),
            new UserException('FieldPath field names may not start with \'$\''),
        ];

        // Regression: a mongoexport fatal that matches none of the branches above and whose
        // "Failed:" reason does not contain the word "command" (so the /(Failed:.*?command)/
        // fallback misses it too) used to fall through to the rethrow, killing the job with an
        // opaque "Internal Server Error occurred." (exit 2) instead of telling the user what
        // mongoexport had already reported.
        //
        // The stderr below is a verbatim capture from mongodb-database-tools 100.15.0 exporting a
        // non-empty collection with --query '{"$foo": 1}'. Note it is NOT the same case as the
        // "FieldPath field names may not start with '$'" branch above - that branch's marker does
        // not appear here, which is exactly why this message used to fall through.
        yield 'unclassified mongoexport failure surfaces its own "Failed:" reason' => [
            new ProcessFailedException($this->createMockInstanceOfProcess(
                '2026-08-18T06:35:37.390+0000' . "\t" . 'connected to: mongodb://mongo:27017/' . "\n" .
                '2026-08-18T06:35:37.401+0000' . "\t" . 'Failed: (BadValue) unknown top level ' .
                'operator: $foo. If you have a field name that starts with a \'$\' symbol, ' .
                'consider using $getField or $setField.' . "\n",
            )),
            new UserException('Export "" failed. MongoDB export tool reported: (BadValue) unknown ' .
                'top level operator: $foo. If you have a field name that starts with a \'$\' ' .
                'symbol, consider using $getField or $setField.'),
        ];
    }

    /**
     * The fallback added for the case above must not become a catch-all: when mongoexport reported
     * no "Failed:" reason at all there is nothing user-actionable to surface, so the original
     * exception has to keep propagating exactly as before (opaque internal error, exit 2).
     *
     * @throws \ReflectionException
     * @throws \Keboola\Component\UserException
     */
    public function testFailureWithoutFailedLineIsStillRethrown(): void
    {
        $mongoException = new ProcessFailedException(
            $this->createMockInstanceOfProcess('Killed' . "\n"),
        );

        $this->expectException(ProcessFailedException::class);

        $class = new ReflectionClass(Export::class);
        $method = $class->getMethod('handleMongoExportFails');
        $exportOptions = new ExportOptions(['name' => '', 'mode' => '']);
        $exportClass = new Export(
            new ExportCommandFactory(new UriFactory(), false),
            [],
            $exportOptions,
            new NullLogger(),
        );
        $method->invoke($exportClass, $mongoException);
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
