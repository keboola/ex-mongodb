<?php

declare(strict_types=1);

namespace MongoExtractor;

use MongoExtractor\Config\DbNode;

class ExportCommandFactory
{

    public function __construct(private UriFactory $uriFactory, private bool $quiet) {}

    /**
     * @throws \Keboola\Component\UserException
     */
    public function create(array $params): string
    {
        $protocol = $params['protocol'] ?? DbNode::PROTOCOL_MONGO_DB;
        $command = ['mongoexport'];

        [$command, $params] = $this->connectionOptions($protocol, $params, $command);

        $command = $this->exportOptions($params, $command);

        return implode(' ', $command);
    }

    protected function addDefaultSort(): string
    {
        return '--sort ' . escapeshellarg('{_id: 1}');
    }

    /**
     * @throws \Keboola\Component\UserException
     */
    protected function connectionOptions(string $protocol, array $params, array $command): array
    {
        if (in_array($protocol, [
            DbNode::PROTOCOL_MONGO_DB_SRV,
            DbNode::PROTOCOL_CUSTOM_URI,
        ], true)) {
            // mongodb+srv:// can be used only in URI parameter
            $uri = (string) $this->uriFactory->create($params);
            $command[] = '--uri ' . escapeshellarg($uri);
        } else {
            // If not mongodb+srv://, then use standard parameters: --host, --db, ...
            // because --uri parameter does not work well with some MongoDB servers (probably a bug).
            // In that case:
            // .... test Connection through PHP driver works OK
            // .... mongoexport with --host parameter works OK
            // .... mongoexport with --uri parameter freezes without writing an error
            // Therefore is --uri parameter used only with mongodb+srv://, where there is no other way.
            $command[] = '--host ' . escapeshellarg($params['host']);
            $command[] = '--port ' . escapeshellarg((string) $params['port']);
            $command[] = '--db ' . escapeshellarg($params['database']);

            if (isset($params['user'])) {
                $command[] = '--username ' . escapeshellarg($params['user']);
                $command[] = '--password ' . escapeshellarg($params['password']);
            }

            if (isset($params['authenticationDatabase'])
                && !empty(trim((string)$params['authenticationDatabase']))
            ) {
                $command[] = '--authenticationDatabase ' . escapeshellarg($params['authenticationDatabase']);
            }
        }

        return [$command, $params];
    }

    protected function exportOptions(array $params, array $command): array
    {
        $command[] = '--collection ' . escapeshellarg($params['collection']);

        foreach (['query', 'sort', 'limit', 'skip'] as $option) {
            if (isset($params[$option]) && !empty(trim((string)$params[$option]))) {
                if ($option === 'query') {
                    $params[$option] = ExportHelper::addQuotesToJsonKeys($params[$option]);
                    $params[$option] = ExportHelper::convertStringIdToObjectId($params[$option]);
                }
                $command[] = '--' . $option . ' ' . escapeshellarg((string)$params[$option]);
            } else if ($option === 'sort') {
                $command[] = $this->addDefaultSort();
            }
        }

        $command[] = '--type ' . escapeshellarg('json');

        if ($this->quiet) {
            $command[] = '--quiet';
        }

        return $command;
    }
}
