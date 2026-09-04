<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\TicketCategory::class);
    }

    /**
     * Prepare inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('network_type') && !$this->has('infrastructure_type')) {
            $this->merge(['infrastructure_type' => $this->network_type]);
        } elseif ($this->has('infrastructure_type') && !$this->has('network_type')) {
            $this->merge(['network_type' => $this->infrastructure_type]);
        }
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
            'infrastructure_type' => ['required', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])],
            'network_type' => ['nullable', 'string'],
            'sla_hours' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
