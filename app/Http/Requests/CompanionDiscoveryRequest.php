<?php

namespace App\Http\Requests;

use App\Enums\TravelStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates query parameters for the companion discovery endpoint.
 *
 * All parameters are optional — passing none returns the full paginated
 * discovery feed (discoverable users, profile-completion-gated, newest-first).
 *
 * Mirrors TripDiscoveryRequest conventions.
 */
class CompanionDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum middleware handles authentication
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Text destination search — matched against preferred_destinations.destination
            'destination'    => ['sometimes', 'nullable', 'string', 'max:200'],

            // Exact Google Places ID — matched against preferred_destinations.place_id
            'place_id'       => ['sometimes', 'nullable', 'string', 'max:100'],

            // Date range the authenticated user wants to travel
            'start_date'     => ['sometimes', 'nullable', 'date'],
            'end_date'        => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],

            // Travel style: must be a valid TravelStyle enum value
            'travel_style'   => ['sometimes', 'nullable', 'string', Rule::in(TravelStyle::values())],

            // Interest IDs: filter companions having at least one matching interest
            'interest_ids'   => ['sometimes', 'nullable', 'array'],
            'interest_ids.*' => ['integer', 'exists:interests,id'],

            // Sorting: profile_completion (default) or newest
            'sort'           => ['sometimes', 'nullable', 'string', Rule::in(['profile_completion', 'newest'])],

            // Pagination
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'travel_style.in'         => 'Invalid travel style. Supported: ' . implode(', ', TravelStyle::values()) . '.',
            'sort.in'                 => 'Invalid sort value. Supported: profile_completion, newest.',
            'interest_ids.*.exists'   => 'One or more interest IDs do not exist.',
        ];
    }

    /**
     * Trim whitespace from text filter fields before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('destination') && is_string($this->destination)) {
            $this->merge(['destination' => trim($this->destination)]);
        }
    }
}
