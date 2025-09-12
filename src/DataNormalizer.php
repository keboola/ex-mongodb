<?php

declare(strict_types=1);

namespace MongoExtractor;

interface DataNormalizer
{
    /**
     * @param array<int, array<string, mixed>|object> $data
     */
    public function normalize(array &$data): void;
}
