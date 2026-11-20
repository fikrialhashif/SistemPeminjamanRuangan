<?php

namespace App\Http\Resources;

use App\Enums\ResourceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category?->name,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'location' => [
                'id' => $this->location?->id,
                'name' => $this->location?->name,
            ],
            'room' => $this->when($this->type === ResourceType::ROOM, fn () => [
                'capacity' => $this->room?->capacity,
                'facilities' => $this->room?->facilities,
                'photo_path' => $this->room?->photo_path,
                'open_time' => $this->room?->open_time,
                'close_time' => $this->room?->close_time,
            ]),
            'asset' => $this->when($this->type === ResourceType::ASSET, fn () => [
                'code' => $this->asset?->code,
                'condition' => $this->asset?->condition,
                'status' => $this->asset?->status,
            ]),
            'rules' => $this->whenLoaded('rules', fn () => [
                'buffer_minutes' => $this->rules->buffer_minutes,
                'min_duration_minutes' => $this->rules->min_duration_minutes,
                'max_duration_minutes' => $this->rules->max_duration_minutes,
                'advance_booking_days' => $this->rules->advance_booking_days,
                'approval_path' => $this->rules->approval_path,
                'cancellation_minutes_before' => $this->rules->cancellation_minutes_before,
            ]),
        ];
    }
}
