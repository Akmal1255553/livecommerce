<?php

declare(strict_types=1);

namespace App\Services;

use App\Logging\StructuredLogger;

abstract class BaseService
{
    public function __construct(
        protected StructuredLogger $logger,
    ) {}
}
