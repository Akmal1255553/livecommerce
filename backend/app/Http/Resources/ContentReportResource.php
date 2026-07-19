<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContentReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentReport */
class ContentReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reporter' => $this->when($this->relationLoaded('reporter'), fn () => $this->reporter !== null ? [
                'id' => $this->reporter->id,
                'username' => $this->reporter->username,
            ] : null),
            'target_type' => $this->target_type->value,
            'target_id' => $this->target_id,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'resolved_by' => $this->when($this->relationLoaded('resolver'), fn () => $this->resolver !== null ? [
                'id' => $this->resolver->id,
                'username' => $this->resolver->username,
            ] : null),
            'resolution_note' => $this->resolution_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
