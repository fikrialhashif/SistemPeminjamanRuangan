<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', 'string', 'in:good,damaged,lost'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
