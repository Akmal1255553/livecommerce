<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface OrderNumberGeneratorInterface
{
    public function generate(): string;
}
