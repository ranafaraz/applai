<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'             => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'confirmed', Password::min(8)],
            'tracking_types'        => ['sometimes', 'array'],
            'tracking_types.*'      => ['string', 'in:job,phd,scholarship,grant,freelance'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'   => 'Email already in use.',
            'password.min'   => 'Min 8 characters.',
        ];
    }
}
