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
        $exportClass = new Export(new ExportCommandFactory(new UriFactory(), false), [], $exportOptions);
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
