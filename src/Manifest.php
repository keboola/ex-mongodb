<?php

namespace MongoExtractor;

use MongoExtractor\Config\ExportOptions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Manifest
{
    protected Filesystem $fs;
    private JsonEncode $jsonEncode;

    public function __construct(private ExportOptions $exportOptions, private string $path, private mixed $primaryKey)
    {
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