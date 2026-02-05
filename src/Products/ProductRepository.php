<?php

namespace App\Products;

use App\Importer\Contracts\RepositoryInterface;
use Doctrine\DBAL\Connection;

class ProductRepository implements RepositoryInterface
{
    public function __construct(
        private Connection $connection
    ) {}

    public function saveBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $valuesSql = [];
        $params = [];

        foreach ($rows as $row) {
            $valuesSql[] = $placeholders;
            foreach ($columns as $column) {
                $params[] = $row[$column];
            }
        }

        $updateSql = implode(
            ', ',
            array_map(fn($col) => "$col = EXCLUDED.$col", $columns)
        );

        $sql = sprintf(
            'INSERT INTO product (%s) VALUES %s
             ON CONFLICT (gtin) DO UPDATE SET %s',
            implode(', ', $columns),
            implode(', ', $valuesSql),
            $updateSql
        );

        $this->connection->executeStatement($sql, $params);
    }
}
