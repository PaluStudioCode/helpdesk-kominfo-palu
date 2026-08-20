<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        $user = $this->user();
        
        if ($this->boolean('is_internal')) {
            return $user->can('replyInternal', $ticket);
        }

        return $user->can('replyPublic', $ticket);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2'],
            'is_internal' => ['required', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'], // max 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.*.max' => 'Ukuran gambar lampiran tidak boleh melebihi 5 MB.',
            'attachments.*.mimes' => 'Format berkas tidak didukung. Harap unggah berkas gambar (JPG, JPEG, PNG).',
        ];
    }
}
