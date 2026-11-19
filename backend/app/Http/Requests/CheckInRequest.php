<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string'],
            'gps_latitude' => ['required', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['required', 'numeric', 'min:0'],
            'gps_timestamp' => ['required', 'date'],
            'gps_is_mock' => ['required', 'boolean'],
            'face_image' => ['nullable', 'string'],
            'face_front_image' => ['nullable', 'string'],
            'face_challenge' => ['nullable', 'string', 'in:blink,turn_left,turn_right,turn'],
            'face_challenge_response' => ['nullable', 'array'],
        ];
    }
}
