<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(Permission::RESOURCES_UPDATE);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(ResourceType::class)],
            'location_id' => ['nullable', 'exists:locations,id'],
            'category' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $type = $this->input('type', $this->route('resource')?->type?->value);

        if ($type === ResourceType::ROOM->value) {
            $rules['capacity'] = ['sometimes', 'integer', 'min:1'];
            $rules['facilities'] = ['nullable', 'array'];
            $rules['open_time'] = ['nullable', 'date_format:H:i'];
            $rules['close_time'] = ['nullable', 'date_format:H:i'];
        }

        if ($type === ResourceType::ASSET->value) {
            $resourceId = $this->route('resource')?->asset?->id;
            $rules['code'] = ['sometimes', 'string', 'unique:assets,code,'.$resourceId];
            $rules['condition'] = ['nullable', 'string'];
        }

        return $rules;
    }
}
