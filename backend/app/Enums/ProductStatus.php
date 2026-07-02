<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OutOfStock = 'out_of_stock';
    case Archived = 'archived';
}
