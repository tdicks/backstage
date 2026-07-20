<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'mobile_number' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string'],
            'hide_from_directory' => ['nullable', 'boolean'],
            'hide_from_slot_proposals' => ['nullable', 'boolean'],
            'slot_coverage' => ['nullable', 'array'],
            'slot_coverage.*' => ['string'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*.enabled' => ['nullable', 'boolean'],
            'notification_preferences.*.popup' => ['nullable', 'boolean'],
            'notification_preferences.*.email' => ['nullable', 'boolean'],
        ];
    }
}
