<?php

namespace App\Importer;

use App\Importer\Contracts\FeedReaderInterface;

class CsvFeedReader implements FeedReaderInterface
{
    public function type(): string
    {
        return 'csv';
    }

    public function read(string $path): iterable
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV file: {$path}");
        }

        $headers = fgetcsv($handle, 0, ',', '"', ''); //skip the header

        if ($headers === false) {
            fclose($handle);
            return;
        }

        // Normalize headers
        $headers = array_map(
            fn($h) => strtolower(trim(ltrim($h, "\xEF\xBB\xBF"))),
            $headers
        );

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            // Skip empty lines
            if ($row === [null] || count($row) === 0) {
                continue;
            }

            // Skip malformed rows
            if (count($row) !== count($headers)) {
                continue;
            }

            yield array_combine($headers, $row);
        }

        fclose($handle);
    }
}
