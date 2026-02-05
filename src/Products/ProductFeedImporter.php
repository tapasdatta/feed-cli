<?php

namespace App\Products;

use App\Importer\BaseImporter;

class ProductFeedImporter extends BaseImporter
{
    public function key(): string
    {
        return 'products';
    }
}
