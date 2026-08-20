<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user_model); // will alias in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 
                'string', 
                'email', 
                'max:150', 
                Rule::unique('users', 'email')->ignore($this->route('user'))
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^(08|628)[0-9]{8,13}$/'],
            'role' => ['required', 'in:admin,technician,opd_user'],
            'status' => ['required', 'in:active,inactive'],
            'department_id' => [
                'required_if:role,opd_user', 
                'nullable', 
                'exists:departments,id',
                Rule::unique('users', 'department_id')
                    ->ignore($this->route('user'))
                    ->where(fn ($query) => $query->where('role', 'opd_user')->whereNull('deleted_at'))
                    ->when($this->input('role') === 'opd_user', fn ($rule) => $rule, fn () => null)
            ],
        ];
    }
}
