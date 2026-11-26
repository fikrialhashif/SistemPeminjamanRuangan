<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'radius' => ['integer', 'min:10'],
            'polygon' => ['nullable', 'array'],
            'open_time' => ['sometimes', 'required_with:close_time', 'date_format:H:i'],
            'close_time' => ['sometimes', 'required_with:open_time', 'date_format:H:i', 'after:open_time'],
        ];
    }
}
