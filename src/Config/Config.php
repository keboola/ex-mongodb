<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use InvalidArgumentException;
use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    /**
     * @return array<string, mixed>
     */
    public function getDb(): array
    {
        $db = $this->getArrayValue(['parameters', 'db']);
        if ($this->isSshEnabled()) {
            $db['host'] = '127.0.0.1';
            $db['port'] = $db['ssh']['localPort'];
        }

        return  $db;
    }

    public function isSshEnabled(): bool
    {
        return (bool) $this->getValue(['parameters', 'db', 'ssh', 'enabled'], false);
    }

    /**
     * @return array<string, string|int|bool|array<string,string>>
     */
    public function getSshOptions(): array
    {
        return $this->getArrayValue(['parameters', 'db', 'ssh']);
    }

    /**
     * @return array<string,string>
     */
    public function getSshKeys(): array
    {
        return $this->getArrayValue(['parameters', 'db', 'ssh', 'keys']);
    }

    /**
     * @return \MongoExtractor\Config\ExportOptions[]
     * @throws \Keboola\Component\UserException
     */
    public function getExportOptions(): array
    {
        $exportOptions = [];

        try {
            $exports = $this->getArrayValue(['parameters', 'exports']);
            foreach ($exports as $export) {
                $exportOptions[] = new ExportOptions($export);
            }
        } catch (InvalidArgumentException) {
            $exportOptions[] = new ExportOptions($this->getParameters());
        }

        return $exportOptions;
    }

    public function isQuietModeEnabled(): bool
    {
        return (bool) $this->getValue(['parameters', 'quiet'], false);
    }
}
