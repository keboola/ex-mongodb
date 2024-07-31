<?php

declare(strict_types=1);

namespace MongoExtractor;

use Keboola\Component\Config\DatatypeSupport;
use Keboola\Component\Manifest\ManifestManager;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptions;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptionsSchema;

class Manifest
{
    private ManifestManager $manifestManager;

    /**
     * @param array<int, string> $primaryKey
     * @param array<int, string> $columns
     */
    public function __construct(
        private readonly DatatypeSupport $datatypeSupport,
        private readonly bool $isIncrementalFetching,
        private readonly string $path,
        private readonly array $primaryKey,
        private readonly array $columns,
    ) {
        $directoryPath = str_replace('/out/tables', '', pathinfo($this->path, PATHINFO_DIRNAME));
        $this->manifestManager = new ManifestManager($directoryPath);
    }

    /**
     * @throws \Keboola\Component\Manifest\ManifestManager\Options\OptionsValidationException
     */
    public function generate(): void
    {
        $manifest = new ManifestOptions();
        $manifest->setIncremental($this->isIncrementalFetching);
        foreach ($this->columns as $column) {
            $manifest->addSchema(new ManifestOptionsSchema(
                $column,
                null,
                true,
                in_array($column, $this->primaryKey, true),
            ));
        }

        $this->manifestManager->writeTableManifest(
            pathinfo($this->path, PATHINFO_FILENAME),
            $manifest,
            $this->datatypeSupport->usingLegacyManifest(),
        );
    }
}
