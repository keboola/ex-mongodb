<?php

declare(strict_types=1);

namespace MongoExtractor\Parser;

use Exception;
use Keboola\Component\UserException;
use Keboola\CsvMap\Exception\BadConfigException;
use Keboola\CsvMap\Exception\BadDataException;
use Keboola\CsvMap\Mapper;
use Keboola\CsvTable\Table;
use Nette\Utils\Strings;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Throwable;
use TypeError;

class Mapping implements ParserInterface
{
    /** @var array<string, mixed> */
    private array $mapping;
    private bool $includeParentInPK;
    private string $path;
    private string $name;
    private Filesystem $filesystem;
    /** @var array<string, array{path: string, primaryKey: array<string>|string|null}>. */
    private array $manifestData = [];

    /**
     * @param array<string, mixed> $mapping
     */
    public function __construct(
        string $name,
        array $mapping,
        bool $includeParentInPK,
        string $outputPath,
    ) {
        $this->name = $name;
        $this->mapping = $mapping;
        $this->includeParentInPK = $includeParentInPK;
        $this->path = $outputPath;
        $this->filesystem = new Filesystem();
    }

    /**
     * Parses provided data and writes to output files
     * @param array<int, object> $data
     * @throws \Keboola\Component\UserException
     * @throws \Exception
     */
    public function parse(array $data): void
    {
        $userData = $this->includeParentInPK ? ['parentId' => md5(serialize($data))] : [];
        $mapper = new Mapper($this->mapping, false, $this->name);
        try {
            $mapper->parse($data, $userData);
        } catch (BadConfigException|BadDataException $e) {
            throw new UserException(sprintf('Invalid mapping configuration: %s', $e->getMessage()));
        } catch (TypeError) { // @phpstan-ignore-line
            throw new UserException('CSV writing error. Header and mapped documents must be scalar values.');
        }

        foreach ($mapper->getCsvFiles() as $file) {
            if ($file !== null) {
                $name = Strings::webalize($file->getName());
                $outputCsv = $this->path . '/' . $name . '.csv';

                $content = file_get_contents($file->getPathname());

                if (!$this->filesystem->exists($outputCsv)) {
                    $this->prependHeader($file, $content);
                }

                try {
                    if (@file_put_contents($outputCsv, $content, FILE_APPEND | LOCK_EX) === false) {
                        throw new Exception('Failed write to file "' . $outputCsv . '"');
                    }
                } catch (Throwable $e) {
                    throw new Exception('Failed write to file "' . $outputCsv . '"');
                }

                $this->manifestData[$outputCsv] = [
                    'path' => $outputCsv . '.manifest',
                    'primaryKey' => $file->getPrimaryKey(true),
                ];

                $this->filesystem->remove($file->getPathname());
            }
        }
    }

    protected function prependHeader(Table $file, string &$content): void
    {
        $header = $file->getHeader();
        if ($header !== []) {
            $content = sprintf(
                '"%s"%s%s',
                implode('"' . $file->getDelimiter() . '"', $file->getHeader()),
                PHP_EOL,
                $content
            );
        }
    }

    /**
     * @return array<string, array{path: string, primaryKey: array<string>|string|null}>
     */
    public function getManifestData(): array
    {
        return $this->manifestData;
    }
}
