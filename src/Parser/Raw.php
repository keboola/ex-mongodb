<?php

declare(strict_types=1);

namespace MongoExtractor\Parser;

use Keboola\Csv\CsvWriter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Raw implements ParserInterface
{
    private CsvWriter $outputFile;
    private string $filename;
    private bool $setIdAsPrimaryKey = true;

    /**
     * @throws \Keboola\Csv\InvalidArgumentException
     * @throws \Keboola\Csv\Exception
     */
    public function __construct(string $name, string $outputPath)
    {
        $this->filename = $outputPath . '/' . $name . '.csv';

        // create csv file and its header
        $this->outputFile = new CsvWriter($this->filename);
        $this->outputFile->writeRow(['id', 'data']);
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

    public function getManifestData(): array
    {
        return [['path' => $this->filename . '.manifest', 'primaryKey' => $this->setIdAsPrimaryKey ? ['id']: []]];
    }
}
