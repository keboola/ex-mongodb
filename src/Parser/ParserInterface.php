<?php

namespace MongoExtractor\Parser;

interface ParserInterface
{
    public function parse(array $data): void;
    public function getManifestData(): array;
}