<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Department::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'pic_phone' => ['nullable', 'string', 'max:30', 'regex:/^(08|628)[0-9]{8,13}$/'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
