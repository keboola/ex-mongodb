<?php

declare(strict_types=1);

namespace MongoExtractor;

use Generator;
use MongoExtractor\Config\ExportOptions;
use MongoExtractor\Parser\Mapping;
use MongoExtractor\Parser\ParserInterface;
use MongoExtractor\Parser\Raw;
use Nette\Utils\Strings;
use Symfony\Component\Console\Output\ConsoleOutput;

class Parse
{
    private string $name;
    /** @var array<string, mixed> */
    private array $mapping;
    private ConsoleOutput $consoleOutput;

    public function __construct(
        private ExportOptions $exportOptions,
        private string $path,
    ) {
        $this->name = Strings::webalize($exportOptions->getName());
        $this->mapping = $exportOptions->getMapping();
        $this->consoleOutput = new ConsoleOutput;
    }

    /**
     * Parses exported json and creates .csv and .manifest files
     * @return array<string, array{path: string, primaryKey: array<int, string>, columns: array<int, string>}>
     * @throws \Keboola\Csv\Exception
     * @throws \Keboola\Csv\InvalidArgumentException
     */
    public function parse(Generator $exportOutput): array
    {
        $parser = $this->getParser();
        $parsedDocumentsCount = 0;
        $lastLoggedCount = null;

        foreach ($exportOutput as $output) {
            foreach ($output as $array) {
                $parser->parse($array);
                if (!empty($array)) {
                    $parsedDocumentsCount++;
                }
                if ($parsedDocumentsCount % 5e3 === 0
                    && $parsedDocumentsCount !== 0
                    && $parsedDocumentsCount !== $lastLoggedCount
                ) {
                    $lastLoggedCount = $parsedDocumentsCount;
                    $this->consoleOutput->writeln('Parsed ' . $parsedDocumentsCount . ' records.');
                }
            }
        }

        if ($parsedDocumentsCount === 0) {
            $parser->parse([]); //create csv with headers only
        }

        $this->consoleOutput->writeln(sprintf(
            'Done "%s", parsed %d %s in total',
            $this->name,
            $parsedDocumentsCount,
            $parsedDocumentsCount === 1 ? 'record' : 'records'
        ));

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
