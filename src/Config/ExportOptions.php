<?php

namespace MongoExtractor\Config;

use Keboola\Component\UserException;

class ExportOptions
{
    public const MODE_MAPPING = 'mapping';
    public const MODE_RAW = 'raw';

    private ?string $id;
    private bool $enabled;
    private bool $incrementalFetching;
    private string $name;
    private array $mapping;
    private string $mode;
    private ?int $limit;
    private array $lastValueOptions;
    private ?string $incrementalFetchingColumn;
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
        $this->enabled = (bool) ($exportOptions['enabled'] ?? false);
        $this->id = $exportOptions['id'] ?? null;
        $this->incrementalFetchingColumn = $exportOptions['incrementalFetchingColumn'] ?? null;
        $this->incrementalFetching = $exportOptions['incremental'] ?? false;
        $this->name = $exportOptions['name'];
        $this->mode = $exportOptions['mode'];
        $this->mapping = $exportOptions['mapping'] ?? [];
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
        return $this->incrementalFetchingColumn;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function getSort(): string
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