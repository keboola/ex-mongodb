<?php

declare(strict_types=1);

namespace MongoExtractor;

use MongoExtractor\Config\ExportOptions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Manifest
{
    protected Filesystem $fs;
    private JsonEncode $jsonEncode;

    /**
     * @param array<int, string>|string|null $primaryKey
     */
    public function __construct(
        private ExportOptions $exportOptions,
        private string $path,
        private array|string|null $primaryKey
    ) {
        $this->fs = new Filesystem();
        $this->jsonEncode = new JsonEncode;
    }

    public function generate(): void
    {
        $manifest = [
            'primary_key' => $this->primaryKey,
            'incremental' => $this->exportOptions->isIncrementalFetching(),

        ];

        $this->fs->dumpFile(
            $this->path,
            $this->jsonEncode->encode($manifest, JsonEncoder::FORMAT)
        );
    }
}
