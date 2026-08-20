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
            'bio'                   => ['nullable', 'string', 'max:1000'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'country'               => ['nullable', 'string', 'max:100'],
            'languages'             => ['nullable', 'array', 'max:10'],
            'languages.*'           => ['string', 'max:50'],
            'travel_style'          => ['nullable', 'string', Rule::in(TravelStyle::values())],
            // Budget preferences — optional; represent comfortable budget range for trips
            'preferred_budget_min'  => ['nullable', 'numeric', 'min:0'],
            'preferred_budget_max'  => $this->budgetMaxRules(),
        ];
    }

    /**
     * When preferred_budget_min is provided and non-null, budget_max must be >= it.
     * When budget_min is absent, gte would compare against null/0 — so we skip the constraint.
     *
     * @return array<int, mixed>
     */
    private function budgetMaxRules(): array
    {
        $rules = ['nullable', 'numeric', 'min:0'];

        // Only enforce gte when the caller also provided a non-null budget_min
        if ($this->filled('preferred_budget_min')) {
            $rules[] = 'gte:preferred_budget_min';
        }

        return $rules;
    }
}
