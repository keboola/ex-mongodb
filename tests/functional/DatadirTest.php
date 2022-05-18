<?php

declare(strict_types = 1);

namespace MongoExtractor\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\Temp\Temp;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatadirTest extends DatadirTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        putenv('SSH_PRIVATE_KEY=' . file_get_contents('/root/.ssh/id_rsa'));
        putenv('SSH_PUBLIC_KEY=' . file_get_contents('/root/.ssh/id_rsa.pub'));
    }

    public function getTemp(): Temp
    {
        return $this->temp;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->closeSshTunnels();

        $testProjectDir = $this->getTestFileDir() . '/' . $this->dataName();

        // Load setUp.php file - used to init database state
        $setUpPhpFile = $testProjectDir . '/setUp.php';
        if (file_exists($setUpPhpFile)) {
            // Get callback from file and check it
            $initCallback = require $setUpPhpFile;
            if (!is_callable($initCallback)) {
                throw new RuntimeException(sprintf('File "%s" must return callback!', $setUpPhpFile));
            }

            // Invoke callback
            $initCallback($this);
        }
    }

    protected function closeSshTunnels(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }
}
