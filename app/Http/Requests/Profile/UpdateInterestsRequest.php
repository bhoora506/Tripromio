<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'interest_ids'   => ['required', 'array', 'max:20'],
            'interest_ids.*' => ['required', 'integer', 'exists:interests,id'],
        ];
    }
}
