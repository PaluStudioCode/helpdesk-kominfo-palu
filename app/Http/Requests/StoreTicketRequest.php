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
        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'location_details' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'], // max 5MB image only
        ];
    }

    public function messages()
    {
        return [
            'attachments.*.max' => 'Ukuran gambar lampiran tidak boleh melebihi 5 MB.',
            'attachments.*.mimes' => 'Format berkas tidak didukung. Harap unggah berkas gambar (JPG, JPEG, PNG).',
        ];
    }
}
