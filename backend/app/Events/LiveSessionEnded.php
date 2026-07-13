<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LiveSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveSessionEnded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public LiveSession $session) {}
}
