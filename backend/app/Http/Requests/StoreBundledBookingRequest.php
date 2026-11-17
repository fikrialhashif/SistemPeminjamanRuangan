<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBundledBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resource_ids' => ['required', 'array', 'min:2'],
            'resource_ids.*' => ['integer', 'distinct', 'exists:resources,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'title' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
