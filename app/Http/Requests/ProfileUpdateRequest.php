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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone_number' => [
                'required',
                'string',
                'regex:/^(08|628)[0-9]{8,13}$/'
            ]
        ];
    }
    
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Nomor WhatsApp harus berupa format Indonesia yang valid (contoh: 08123456789 atau 628123456789).'
        ];
    }
}
