<?php

declare(strict_types=1);

namespace MongoExtractor;

interface DataNormalizer
{
    /**
     * @param array<string, mixed> $data
     */
    public function normalize(array &$data): void;
}
