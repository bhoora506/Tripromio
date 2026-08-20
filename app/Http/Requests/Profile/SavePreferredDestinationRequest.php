<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePreferredDestinationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'max:200'],
            'place_id'    => ['nullable', 'string', 'max:100'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $user = $this->user();
            $data = $validator->validated();
            
            // Exclude current destination ID if it's an update
            $currentId = $this->route('destination') ? $this->route('destination')->id : null;

            if (! $currentId) {
                $count = $user->preferredDestinations()->count();
                if ($count >= 50) {
                    $validator->errors()->add('destination', 'You have reached the maximum limit of 50 preferred destinations.');
                    return;
                }
            }

            $existing = $user->preferredDestinations()
                ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                ->get();

            $newPlaceId = $data['place_id'] ?? null;
            $newNormalizedDest = strtolower(trim($data['destination']));

            foreach ($existing as $dest) {
                // Case A: Both have place_id
                if ($newPlaceId && $dest->place_id && $newPlaceId === $dest->place_id) {
                    $validator->errors()->add('destination', 'You have already added this destination.');
                    return;
                }

                // Case B & C: No place_id on one or both, fallback to string match
                if (strtolower(trim($dest->destination)) === $newNormalizedDest) {
                    $validator->errors()->add('destination', 'You have already added this destination.');
                    return;
                }
            }
        });
    }
}
