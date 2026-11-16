<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'face_image' => ['required', 'string'],
            'gps_latitude' => ['required', 'numeric'],
            'gps_longitude' => ['required', 'numeric'],
            'gps_accuracy' => ['required', 'numeric'],
            'asset_code' => ['required', 'string'],
        ];
    }
}
