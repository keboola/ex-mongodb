<?php

declare(strict_types=1);

namespace MongoExtractor;

/**
 * Applies several normalizers to the same data, in the given order.
 */
final class NormalizerChain implements DataNormalizer
{
    /**
     * @param array<int, DataNormalizer> $normalizers
     */
    public function __construct(
        private array $normalizers,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function normalize(array &$data): void
    {
        foreach ($this->normalizers as $normalizer) {
            $normalizer->normalize($data);
        }
    }
}
