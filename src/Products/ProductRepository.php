<?php

namespace App\Products;

use App\Importer\Contracts\RepositoryInterface;

class ProductRepository implements RepositoryInterface
{
    public function saveBatch(array $rows): void
    {
        //
    }
}
