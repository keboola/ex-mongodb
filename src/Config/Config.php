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
            $db['port'] = '33006';
        }

        return $db;
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
        $db = $this->getArrayValue(['parameters', 'db']);
        $sshOptions = $db['ssh'];
        $sshOptions['privateKey'] = $db['ssh']['keys']['#private'] ?? $db['ssh']['keys']['private'];
        $sshOptions['sshPort'] = 22;
        $sshOptions['localPort'] = '33006';
        $sshOptions['remoteHost'] = $db['host'];
        $sshOptions['remotePort'] = $db['port'];

        return $sshOptions;
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
