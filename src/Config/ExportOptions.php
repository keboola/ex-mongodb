<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use Keboola\Component\UserException;
use MongoExtractor\ExportHelper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ExportOptions
{
    /** Upper bound for "incrementalFetchingOverlap", one year in seconds. */
    public const MAX_INCREMENTAL_FETCHING_OVERLAP = 31536000;

    public const MODE_MAPPING = 'mapping';
    public const MODE_RAW = 'raw';

    private ?string $id;
    private bool $enabled;
    private bool $incrementalFetching;
    private string $name;
    /** @var array<string, mixed> */
    private array $mapping;
    private string $mode;
    private ?int $limit;
    /** @var array<string, int|string|false> */
    private array $lastValueOptions;
    private ?string $incrementalFetchingColumn;
    private ?int $incrementalFetchingOverlap;
    private ?string $query;
    private ?string $collection;
    private ?string $sort;
    private ?int $skip;
    private bool $includeParentInPK;

    /**
     * @param array<string, mixed> $exportOptions
     * @throws \Keboola\Component\UserException
     */
    public function __construct(array $exportOptions)
    {
        $this->enabled = (bool) ($exportOptions['enabled'] ?? true);
        $this->id = isset($exportOptions['id']) ? (string) $exportOptions['id'] : null;
        $this->incrementalFetchingColumn = $exportOptions['incrementalFetchingColumn'] ?? null;
        $overlap = $exportOptions['incrementalFetchingOverlap'] ?? null;
        $this->incrementalFetchingOverlap = ($overlap === null || $overlap === '') ? null : (int) $overlap;
        $this->incrementalFetching = $exportOptions['incremental'] ?? false;
        $this->name = $exportOptions['tableName'] ?? $exportOptions['name'];
        $this->mode = $exportOptions['mode'];
        if (isset($exportOptions['mapping'])) {
            ExportHelper::removeTypesInMappingKeys($exportOptions['mapping']);
        } else {
            $exportOptions['mapping'] = [];
        }
        $this->mapping = $exportOptions['mapping'];
        $this->limit = !empty($exportOptions['limit']) ? (int) $exportOptions['limit'] : null;
        $this->query = $exportOptions['query'] ?? null;
        $this->collection = $exportOptions['collection'] ?? null;
        $this->sort = $exportOptions['sort'] ?? null;
        $this->skip = !empty($exportOptions['skip']) ? (int) $exportOptions['skip'] : null;
        $this->includeParentInPK = (bool) ($exportOptions['includeParentInPK'] ?? false);
        $this->setLastValueOptions();

        if ($this->mode === ExportOptions::MODE_MAPPING && empty($this->mapping)) {
            throw new UserException('Mapping cannot be empty in "mapping" export mode.');
        }
    }

    public function isIncrementalFetching(): bool
    {
        return $this->incrementalFetching;
    }

    public function hasIncrementalFetchingColumn(): bool
    {
        return (bool) ($this->incrementalFetchingColumn ?? false);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    private function setLastValueOptions(): void
    {
        if ($this->limit !== null) {
            $this->lastValueOptions = [
                'limit' => 1,
                'skip' => $this->limit - 1,
                'sort' => json_encode([$this->incrementalFetchingColumn => 1]),
            ];
        } else {
            $this->lastValueOptions = [
                'limit' => 1,
                'sort' => json_encode([$this->incrementalFetchingColumn => -1]),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastValueOptions(): array
    {
        return $this->lastValueOptions;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getIncrementalFetchingColumn(): string
    {
        return $this->incrementalFetchingColumn ?? '';
    }

    public function getIncrementalFetchingOverlap(): ?int
    {
        return $this->incrementalFetchingOverlap;
    }

    /**
     * Validates "incrementalFetchingOverlap" against the rest of one export/row configuration.
     *
     * Shared by ConfigRowDefinition and OldConfigDefinition so both config formats stay in sync.
     *
     * @param array<string, mixed> $exportOptions
     */
    public static function validateIncrementalFetchingOverlap(array $exportOptions): void
    {
        $overlap = $exportOptions['incrementalFetchingOverlap'] ?? null;
        if ($overlap === null) {
            return;
        }

        if (is_int($overlap)) {
            $seconds = $overlap;
        } elseif (is_string($overlap) && ctype_digit($overlap)) {
            // A digit string above PHP_INT_MAX saturates on cast, so the bound is checked after it.
            $seconds = (int) $overlap;
        } else {
            throw new InvalidConfigurationException(
                'The "incrementalFetchingOverlap" must be a non-negative integer (number of seconds).',
            );
        }

        if ($seconds < 0 || $seconds > self::MAX_INCREMENTAL_FETCHING_OVERLAP) {
            throw new InvalidConfigurationException(sprintf(
                'The "incrementalFetchingOverlap" must be between 0 and %d seconds.',
                self::MAX_INCREMENTAL_FETCHING_OVERLAP,
            ));
        }

        if (!isset($exportOptions['incrementalFetchingColumn'])
            || $exportOptions['incrementalFetchingColumn'] === ''
        ) {
            throw new InvalidConfigurationException(
                'The "incrementalFetchingOverlap" can be used only together with "incrementalFetchingColumn".',
            );
        }

        // With a "limit" the saved state is not the maximum value in the collection: it is the
        // last exported document, read as "limit 1, skip limit-1" in ascending order from the
        // start of the query window. An overlap moves that start backwards, so the same documents
        // are counted again and the saved state moves backwards too. The export would then repeat
        // the same window on every run and would never reach newer documents.
        $limit = $exportOptions['limit'] ?? null;
        if (is_numeric($limit) && (int) $limit > 0) {
            throw new InvalidConfigurationException(
                'The "incrementalFetchingOverlap" cannot be used together with "limit", because the '
                . 'saved state would move backwards and the export would never reach newer documents.',
            );
        }
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
    }

    public function isIncludeParentInPK(): bool
    {
        return $this->includeParentInPK;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'collection' => $this->collection,
            'query' => $this->query,
            'sort' => $this->sort,
            'limit' => $this->limit,
            'skip' => $this->skip,
        ];
    }
}
