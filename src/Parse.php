<?php

declare(strict_types=1);

namespace MongoExtractor;

use MongoExtractor\Config\ExportOptions;
use MongoExtractor\Parser\Mapping;
use MongoExtractor\Parser\ParserInterface;
use MongoExtractor\Parser\Raw;
use Nette\Utils\Strings;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class Parse
{
    private string $name;
    /** @var array<int|string, mixed> */
    private array $mapping;
    private ConsoleOutput $consoleOutput;
    private JsonDecode $jsonDecoder;

    public function __construct(
        private ExportOptions $exportOptions,
        private string $path,
    ) {
        $this->name = Strings::webalize($exportOptions->getName());
        $this->mapping = $exportOptions->getMapping();
        $this->consoleOutput = new ConsoleOutput;
        $this->jsonDecoder = new JsonDecode;
    }

    /**
     * Parses exported json and creates .csv and .manifest files
     * @param array<int, string> $exportOutput
     * @return array<int, array{path: string, primaryKey: array<int, string>|string}>
     * @throws \Keboola\Csv\Exception
     * @throws \Keboola\Csv\InvalidArgumentException
     */
    public function parse(array $exportOutput): array
    {
        $this->consoleOutput->writeln('Parsing "' . $this->name . '"');

        $parser = $this->getParser();
        $parsedDocumentsCount = 0;
        $skippedDocumentsCount = 0;
        if (empty($exportOutput)) {
            $parser->parse([]);
        } else {
            foreach ($exportOutput as $line) {
                try {
                    $data = trim($line) !== ''
                        ? [$this->jsonDecoder->decode($line, JsonEncoder::FORMAT)]
                        : [];
                    $parser->parse($data);
                    if ($parsedDocumentsCount % 5e3 === 0 && $parsedDocumentsCount !== 0) {
                        $this->consoleOutput->writeln('Parsed ' . $parsedDocumentsCount . ' records.');
                    }
                } catch (NotEncodableValueException $notEncodableValueException) {
                    $this->consoleOutput->writeln('Could not decode JSON: ' . substr($line, 0, 80) . '...');
                    $skippedDocumentsCount++;
                } finally {
                    $parsedDocumentsCount++;
                }
            }
        }

        if ($skippedDocumentsCount !== 0) {
            $this->consoleOutput->writeln('Skipped documents: ' . $skippedDocumentsCount);
        }
        $this->consoleOutput->writeln('Done "' . $this->name . '"');

        return $parser->getManifestData();
    }

    /**
     * @param array<string, mixed>|string|int|float $data
     */
    public static function saveStateFile(string $outputPath, array|string|int|float $data): void
    {
        $filename = $outputPath . '/../state.json';
        $saveData = [
            'lastFetchedRow' => $data,
        ];
        file_put_contents($filename, json_encode($saveData));
    }

    /**
     * @throws \Keboola\Csv\Exception
     * @throws \Keboola\Csv\InvalidArgumentException
     */
    protected function getParser(): ParserInterface
    {
        if ($this->exportOptions->getMode() === ExportOptions::MODE_RAW) {
            $parser = new Raw($this->name, $this->path);
        } else {
            $parser = new Mapping(
                $this->name,
                $this->mapping,
                $this->exportOptions->isIncludeParentInPK(),
                $this->path,
            );
        }

        return $parser;
    }
}
