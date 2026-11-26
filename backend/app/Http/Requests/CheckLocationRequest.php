<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gps_latitude' => ['required', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['required', 'numeric', 'min:0'],
            'gps_timestamp' => ['required', 'date'],
            'gps_is_mock' => ['required', 'boolean'],
        ];
    }
}
