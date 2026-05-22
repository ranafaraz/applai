<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'subject'   => 'required|string|max:998',
            'body'      => 'required|string',
            'type'      => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }
}
