<?php

declare(strict_types=1);

namespace MongoExtractor\Tests\Traits;

use Symfony\Component\Process\Process;

trait ImportDatasetTrait
{
    public static function importDatatasetNoAuthDb(string $collection, string $dataset): void
    {
        $process = new Process([
            'mongoimport',
            '--host',
            'mongodb',
            '--db',
            'test',
            '--collection',
            $collection,
            '--drop',
            '--jsonArray',
            '--file',
            sprintf('%s/../datasets/%s', __DIR__, $dataset),
        ]);

        $process->mustRun();
    }

    public static function importDatatasetAuthDb(string $collection, string $dataset): void
    {
        $process = new Process([
            'mongoimport',
            '--host',
            'mongodb-auth',
            '--username',
            'user',
            '--password',
            'p#a!s@sw:o&r%^d',
            '--db',
            'test',
            '--collection',
            $collection,
            '--drop',
            '--jsonArray',
            '--file',
            sprintf('%s/../datasets/%s', __DIR__, $dataset),
        ]);

        $process->mustRun();
    }

    public static function importDatatasetCluster(string $collection, string $dataset): void
    {
        $process = new Process([
            'mongoimport',
            '--host',
            'node1.mongodb.cluster.local',
            '--ssl',
            '--db',
            'test',
            '--collection',
            $collection,
            '--drop',
            '--jsonArray',
            '--file',
            sprintf('%s/../datasets/%s', __DIR__, $dataset),
        ]);

        $process->mustRun();
    }
}
