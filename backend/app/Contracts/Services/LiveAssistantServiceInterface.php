<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Collection;

interface LiveAssistantServiceInterface
{
    /**
     * Rule-based MVP suggestions for the live host (ADR-012).
     *
     * @return Collection<int, \App\DTOs\Live\LiveAssistantSuggestionData>
     */
    public function suggestionsForHost(User $seller, string $sessionId): Collection;
}
