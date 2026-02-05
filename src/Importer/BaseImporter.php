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

    public function import(string $path, string $type): ImportResult
    {
        $reader = $this->readerResolver->resolve($type);
        $result = new ImportResult();

        $batch = [];

        foreach ($reader->read($path) as $row) {
            $result->processed++;

            if (! $this->validator->validate($row)) {
                $result->skipped++;
                $result->errors[] = $this->validator->errors();
                continue;
            }

            $batch[] = $this->mapper->map($row);

            if (count($batch) >= $this->batchSize) {
                $this->repository->saveBatch($batch);
                $batch = [];
            }
        }

        // flush remaining rows
        if ($batch !== []) {
            $this->repository->saveBatch($batch);
        }

        return $result;
    }
}
