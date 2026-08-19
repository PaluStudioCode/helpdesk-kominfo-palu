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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $priorityRule = $this->user()->role === 'admin' 
            ? ['required', 'in:low,medium,high,emergency'] 
            : ['required', 'in:low,medium,high'];

        $rules = [
            'network_type' => ['required', 'string', 'in:fiber_optic,lan,wifi'],
            'category_id' => ['required', 'exists:ticket_categories,id'],
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'location_details' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'priority' => $priorityRule,
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // max 5MB
        ];

        // Jika admin membuat on-behalf, department_id wajib diisi
        if ($this->user()->role === 'admin') {
            $rules['department_id'] = ['required', 'exists:departments,id'];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $category = TicketCategory::find($this->category_id);
            
            if ($category && $category->network_type !== $this->network_type) {
                $validator->errors()->add('category_id', 'Kategori yang dipilih tidak sesuai dengan tipe jaringan.');
            }

            if ($category && $category->status !== 'active') {
                $validator->errors()->add('category_id', 'Kategori gangguan yang dipilih sedang tidak aktif.');
            }
        });
    }

    public function messages()
    {
        return [
            'attachments.*.max' => 'Ukuran berkas lampiran tidak boleh melebihi 5 MB.',
            'attachments.*.mimes' => 'Format berkas tidak didukung. Harap unggah berkas gambar (JPG, PNG) atau dokumen PDF.',
        ];
    }
}
