<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaceEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consent' => ['required', 'accepted'],
            'images' => ['required', 'array', 'min:3'],
            'images.*' => ['required', 'string'],
        ];
    }
}
