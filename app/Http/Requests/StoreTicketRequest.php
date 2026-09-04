<?php

namespace App\Http\Requests;

use App\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Ticket::class);
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
        $user = $this->user();

        // Base rules for common fields
        $rules = [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'location_details' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'], // max 5MB image only
        ];

        // Admin On-Behalf Creation Rules
        if ($user->role === 'admin') {
            $rules['department_id'] = ['required', 'exists:departments,id'];
            $rules['infrastructure_type'] = ['required', 'string', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])];
            $rules['network_type'] = ['nullable', 'string'];
            $rules['category_id'] = ['required', 'exists:ticket_categories,id'];
            $rules['priority'] = ['required', 'in:low,medium,high,emergency'];
            $rules['technician_ids'] = ['required', 'array', 'min:1'];
            $rules['technician_ids.*'] = ['exists:users,id'];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $infraType = $this->infrastructure_type ?? $this->network_type;
            if ($this->filled('category_id') && !empty($infraType)) {
                $category = TicketCategory::find($this->category_id);
                
                if ($category && $category->infrastructure_type !== $infraType) {
                    $validator->errors()->add('category_id', 'Kategori yang dipilih tidak sesuai dengan jenis infrastruktur.');
                }

                if ($category && $category->status !== 'active') {
                    $validator->errors()->add('category_id', 'Kategori gangguan yang dipilih sedang tidak aktif.');
                }
            }
        });
    }

    public function messages()
    {
        return [
            'attachments.*.max' => 'Ukuran gambar lampiran tidak boleh melebihi 5 MB.',
            'attachments.*.mimes' => 'Format berkas tidak didukung. Harap unggah berkas gambar (JPG, JPEG, PNG).',
            'technician_ids.required' => 'Harap pilih minimal 1 teknisi penanggung jawab.',
            'technician_ids.min' => 'Harap pilih minimal 1 teknisi penanggung jawab.',
        ];
    }
}
