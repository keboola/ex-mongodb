<?php

declare(strict_types=1);

namespace MongoExtractor\Config;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    // @todo implement your custom getters
    public function getFoo(): string
    {
        return $this->getStringValue(['parameters', 'foo']);
    }
}
