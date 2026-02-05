<?php

namespace App\Importer;

use App\Importer\Contracts\ImporterInterface;
use App\Importer\Contracts\RepositoryInterface;
use App\Importer\Contracts\RowMapperInterface;
use App\Importer\Contracts\RowValidatorInterface;

abstract class BaseImporter implements ImporterInterface
{
    protected int $batchSize = 1000;

    public function __construct(
        protected FeedReaderResolver $readerResolver,
        protected RowMapperInterface $mapper,
        protected RowValidatorInterface $validator,
        protected RepositoryInterface $repository
    ) {}

    public function import(string $path, string $type)
    {
        $reader = $this->readerResolver->resolve($type);


        return $reader;

        $results = new ImportResult();

        return $results;
    }
}
