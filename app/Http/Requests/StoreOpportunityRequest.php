<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:500',
            'type'         => 'nullable|string|max:100',
            'organization' => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:10000',
            'url'          => 'nullable|url|max:2000',
            'status'       => 'nullable|string|max:100',
            'priority'     => 'nullable|in:low,medium,high,urgent',
            'deadline'     => 'nullable|date',
            'notes'        => 'nullable|string|max:10000',
            'contacts'     => 'nullable|array',
            'contacts.*'   => 'integer|exists:contacts,id',
            'tags'         => 'nullable|array',
            'tags.*'       => 'integer|exists:tags,id',
        ];
    }
}
