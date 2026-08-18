<?php

namespace App\Http\Requests\Profile;

use App\Enums\TravelStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'bio'          => ['nullable', 'string', 'max:1000'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'languages'    => ['nullable', 'array', 'max:10'],
            'languages.*'  => ['string', 'max:50'],
            'travel_style' => ['nullable', 'string', Rule::in(TravelStyle::values())],
        ];
    }
}
