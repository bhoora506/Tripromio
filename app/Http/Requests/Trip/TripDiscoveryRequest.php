<?php

namespace App\Http\Requests\Trip;

use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates all query parameters for the trip discovery endpoint.
 *
 * All parameters are optional — passing none returns the full paginated
 * discovery feed (published, future trips, excluding the user's own trips).
 */
class TripDiscoveryRequest extends FormRequest
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
            // Destination: partial search, trimmed, nullable
            'destination' => ['sometimes', 'nullable', 'string', 'max:200'],

            // Date range: user is looking for trips that overlap this window
            'start_date'  => ['sometimes', 'nullable', 'date'],
            'end_date'    => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],

            // Budget range the user is willing to spend
            'budget_min'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'budget_max'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:budget_min'],

            // Trip type must be a valid enum value
            'trip_type'   => ['sometimes', 'nullable', 'string', Rule::in(TripType::values())],

            // Sorting: whitelist only — no arbitrary column names
            'sort'        => ['sometimes', 'string', Rule::in(['newest', 'start_date', 'updated'])],

            // Pagination
            'page'        => ['sometimes', 'integer', 'min:1'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'budget_max.gte'          => 'The maximum budget must be greater than or equal to the minimum budget.',
            'sort.in'                 => 'Invalid sort value. Supported: newest, start_date, updated.',
            'trip_type.in'            => 'Invalid trip type. Supported: ' . implode(', ', TripType::values()) . '.',
        ];
    }

    /**
     * Prepare the data for validation.
     * Trim whitespace from the destination string before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('destination') && is_string($this->destination)) {
            $this->merge(['destination' => trim($this->destination)]);
        }
    }
}
