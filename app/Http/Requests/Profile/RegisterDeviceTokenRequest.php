<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
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
            // FCM tokens are typically ~152-163 characters. 255 provides safe
            // headroom while fitting within MySQL's unique-key length limit.
            'fcm_token' => ['required', 'string', 'max:255'],

            // Platform is explicit so the backend can route notifications
            // to the correct FCM channel in a future phase.
            'platform'  => ['required', 'string', 'in:android,ios'],
        ];
    }
}
