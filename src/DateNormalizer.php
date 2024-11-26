<?php

namespace MongoExtractor;

use MongoDB\BSON\UTCDateTime;

class DateNormalizer
{
    public function __construct(
        private array $mapping = [],
    )
    {
    }

    public function normalize(array &$data): void
    {
        foreach ($data as &$item) {
            foreach ($this->mapping as $path => $mapping) {
                if ($mapping['type'] !== 'date') {
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
                    $current = (new \DateTimeImmutable($current))->format(\DateTimeInterface::ATOM);

                    return;
                }

                if (property_exists($current, '$numberLong')) {
                    $current = (new UTCDateTime((int) $current->{'$numberLong'}))->toDateTimeImmutable()->format(\DateTimeInterface::ATOM);

                    return;
                }
            }
        }
    }
}
