<?php

declare(strict_types=1);

namespace MongoExtractor\Parser;

use Keboola\Csv\CsvWriter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Raw
{
    private CsvWriter $outputFile;

    private Filesystem $filesystem;

    private JsonEncode $jsonEncode;

    private string $filename;

    private array $manifestOptions;

    private bool $setIdAsPrimaryKey = true;

    public function __construct(string $name, string $outputPath, array $manifestOptions)
    {
        $this->filename = $outputPath . '/' . $name . '.csv';

        // create csv file and its header
        $this->outputFile = new CsvWriter($this->filename);
        $this->outputFile->writeRow(['id', 'data']);

        $this->manifestOptions = $manifestOptions;

        $this->filesystem = new Filesystem;
        $this->jsonEncode = new JsonEncode;
    }

    /**
     * Parses provided data and writes to output files
     * @param array $data
     */
    public function parse(array $data): void
    {
        $item = reset($data);

        if (!empty($data)) {
            $this->writerRowToOutputFile($item);
        }
    }

    public function writeManifestFile(): void
    {
        $manifest = [
            'primary_key' => $this->setIdAsPrimaryKey ? ['id']: [],
            'incremental' => $this->manifestOptions['incremental'],
        ];

        $this->filesystem->dumpFile(
            $this->filename . '.manifest',
            $this->jsonEncode->encode($manifest, JsonEncoder::FORMAT)
        );
    }

    private function writerRowToOutputFile(object $item): void
    {
        if (property_exists($item, '_id')) {
            $type = gettype($item->{'_id'});
            if ($type === 'object' && property_exists($item->{'_id'}, '$oid')) {
                $this->outputFile->writeRow([
                    $item->{'_id'}->{'$oid'},
                    \json_encode($item),
                ]);
            } else if (in_array($type, ['double', 'string', 'integer'])) {
                $this->outputFile->writeRow([
                    $item->{'_id'},
                    \json_encode($item),
                ]);
            } else {
                $this->outputFile->writeRow([
                    '',
                    \json_encode($item),
                ]);
                $this->setIdAsPrimaryKey = false;
            }
        } else {
            $this->outputFile->writeRow([
                '',
                \json_encode($item),
            ]);
            $this->setIdAsPrimaryKey = false;
        }
    }
}
