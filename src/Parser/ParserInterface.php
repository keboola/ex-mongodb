<?php

declare(strict_types=1);

namespace MongoExtractor\Parser;

interface ParserInterface
{
    /** @param array<int, object> $data */
    public function parse(array $data): void;

    /** @return array<string, array{path: string, primaryKey: array<int, string>, columns: array<int, string>}> */
    public function getManifestData(): array;
}
