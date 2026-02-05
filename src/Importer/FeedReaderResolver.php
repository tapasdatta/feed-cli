<?php

namespace App\Importer;

use App\Importer\Contracts\FeedReaderInterface;

final class FeedReaderResolver
{
    public function __construct(private array $readers) {}

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
