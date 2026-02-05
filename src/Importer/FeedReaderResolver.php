<?php

namespace App\Importer;

use App\Importer\Contracts\FeedReaderInterface;

class FeedReaderResolver
{
    public function __construct(protected array $readers) {}

    public function resolve(string $type): FeedReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->type() === $type) {
                return $reader;
            }
        }

        throw new \InvalidArgumentException("Unsupported feed type: {$type}");
    }
}
