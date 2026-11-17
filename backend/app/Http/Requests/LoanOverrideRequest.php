<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoanOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['resource_manager', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
