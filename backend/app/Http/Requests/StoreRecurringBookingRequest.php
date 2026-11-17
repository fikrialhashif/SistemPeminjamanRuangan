<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'exists:resources,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'occurrences' => ['required', 'integer', 'between:2,52'],
            'interval_weeks' => ['nullable', 'integer', 'between:1,12'],
            'conflict_policy' => ['required', 'in:skip,reject'],
            'title' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
