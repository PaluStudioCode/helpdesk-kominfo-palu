<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->department);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('departments', 'code')->ignore($this->department->id)
            ],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['required', 'string'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'pic_phone' => ['nullable', 'string', 'max:30', 'regex:/^(08|628)[0-9]{8,13}$/'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
