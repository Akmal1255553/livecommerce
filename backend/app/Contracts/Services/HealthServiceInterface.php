<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Health\HealthStatusData;

interface HealthServiceInterface
{
    public function check(): HealthStatusData;
}
