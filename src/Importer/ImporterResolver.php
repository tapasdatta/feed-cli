<?php

namespace App\Importer;

use InvalidArgumentException;

class ImporterResolver
{
    private iterable $importers;

    public function __construct(iterable $importers)
    {
        $this->importers = $importers;
    }

    public function resolve(string $key)
    {
        foreach ($this->importers as $importer) {
            if ($importer->key() === $key) {
                return $importer;
            }
        }

        throw new InvalidArgumentException("Unsupported import target: {$key}");
    }
}
