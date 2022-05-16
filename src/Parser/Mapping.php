<?php

declare(strict_types=1);

namespace MongoExtractor\Parser;

use Exception;
use Keboola\Component\UserException;
use Keboola\CsvMap\Exception\BadConfigException;
use Keboola\CsvMap\Mapper;
use Nette\Utils\Strings;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Throwable;

class Mapping
{
    private array $mapping;

    private bool $includeParentInPK;

    private string $path;

    private Filesystem $filesystem;

    private JsonEncode $jsonEncode;

    private string $name;

    private array $manifestOptions;

    public function __construct(
        string $name,
        array $mapping,
        bool $includeParentInPK,
        string $outputPath,
        array $manifestOptions
    ) {
        $this->name = $name;
        $this->mapping = $mapping;
        $this->includeParentInPK = $includeParentInPK;
        $this->path = $outputPath;
        $this->manifestOptions = $manifestOptions;

        $this->filesystem = new Filesystem;
        $this->jsonEncode = new JsonEncode;
    }

    /**
     * Parses provided data and writes to output files
     * @throws \Keboola\Component\UserException
     */
    public function parse(array $data): void
    {
        $userData = $this->includeParentInPK ? ['parentId' => md5(serialize($data))] : [];
        $mapper = new Mapper($this->mapping, true, $this->name);
        try {
            $mapper->parse($data, $userData);
        } catch (BadConfigException $e) {
            throw new UserException($e->getMessage());
        }

        foreach ($mapper->getCsvFiles() as $file) {
            if ($file !== null) {
                $name = Strings::webalize($file->getName());
                $outputCsv = $this->path . '/' . $name . '.csv';

                $content = file_get_contents($file->getPathname());

                // csv-map doesn't have option to skip header yet,
                // so we skip header if file exists
                if ($this->filesystem->exists($outputCsv)) {
                    $contentArr = explode("\n", $content);
                    array_shift($contentArr);
                    $content = implode("\n", $contentArr);
                }

                try {
                    if (@file_put_contents($outputCsv, $content, FILE_APPEND | LOCK_EX) === false) {
                        throw new Exception('Failed write to file "' . $outputCsv . '"');
                    }
                } catch (Throwable $e) {
                    throw new Exception('Failed write to file "' . $outputCsv . '"');
                }


                $manifest = [
                    'primary_key' => $file->getPrimaryKey(true),
                    'incremental' => isset($this->manifestOptions['incremental']) && $this->manifestOptions['incremental'],
                ];

                if (!$this->filesystem->exists($outputCsv . '.manifest')) {
                    $this->filesystem->dumpFile(
                        $outputCsv . '.manifest',
                        $this->jsonEncode->encode($manifest, JsonEncoder::FORMAT)
                    );
                }

                $this->filesystem->remove($file->getPathname());
            }
        }
    }
}
