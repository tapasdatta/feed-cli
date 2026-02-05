<?php

namespace App\Importer;

use InvalidArgumentException;

final class ImporterResolver
{

    public function __construct(private array $importers)
    {
        //
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
