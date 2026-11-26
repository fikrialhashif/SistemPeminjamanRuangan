<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(Permission::RESOURCES_CREATE);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ResourceType::class)],
            'location_id' => ['nullable', 'exists:locations,id'],
            'category' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];

        if ($this->input('type') === ResourceType::ROOM->value) {
            $rules['capacity'] = ['required', 'integer', 'min:1'];
            $rules['facilities'] = ['nullable', 'array'];
            $rules['open_time'] = ['nullable', 'date_format:H:i'];
            $rules['close_time'] = ['nullable', 'date_format:H:i', 'after:open_time'];
        }

        if ($this->input('type') === ResourceType::ASSET->value) {
            $rules['code'] = ['required', 'string', 'unique:assets,code'];
            $rules['condition'] = ['nullable', 'string'];
        }

        return $rules;
    }
}
