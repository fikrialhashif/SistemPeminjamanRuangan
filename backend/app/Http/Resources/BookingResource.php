<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'resource' => [
                'id' => $this->resource?->id,
                'name' => $this->resource?->name,
                'type' => $this->resource?->type,
            ],
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'notes' => $this->notes,
            'approvals' => $this->whenLoaded('approvals'),
            'created_at' => $this->created_at,
        ];
    }
}
