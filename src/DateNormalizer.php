<?php

declare(strict_types=1);

namespace MongoExtractor;

use DateTimeImmutable;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

final class DateNormalizer implements DataNormalizer
{
    /**
     * @param array<string, mixed> $mapping
     */
    public function __construct(
        private array $mapping = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function normalize(array &$data): void
    {
        foreach ($data as &$item) {
            foreach ($this->mapping as $path => $mapping) {
                if (!isset($mapping['type']) || $mapping['type'] !== 'date') {
                    continue;
                }

                $keys = explode('.', $path);
                $current = &$item;

                foreach ($keys as $key) {
                    if (property_exists($current, $key)) {
                        $current = &$current->{$key};
                    }
                }

                if (is_string($current)) {
                    $current = (new DateTimeImmutable($current))->format(DateTimeInterface::ATOM);

                    return;
                }

                if (property_exists($current, '$numberLong')) {
                    $current = (new UTCDateTime((int) $current->{'$numberLong'}))
                        ->toDateTime()->format(DateTimeInterface::ATOM);

                    return;
                }
            }
        }
    }
}
