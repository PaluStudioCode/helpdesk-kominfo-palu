<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        $user = $this->user();
        $status = $this->input('status');

        switch ($status) {
            case 'resolved':
                return $user->can('resolve', $ticket);
            case 'closed':
                return $user->can('close', $ticket);
            case 'in_progress': // for reopen
                return $user->can('reopen', $ticket);
            case 'cancelled':
                return $user->can('cancel', $ticket);
            default:
                return false;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'status' => ['required', 'in:in_progress,resolved,closed,cancelled'],
        ];

        if ($this->input('status') === 'resolved') {
            $rules['resolution_note'] = ['required', 'string', 'min:10'];
            $rules['resolution_proofs'] = ['nullable', 'array', 'max:3'];
            $rules['resolution_proofs.*'] = ['file', 'mimes:jpg,jpeg,png', 'max:5120']; // Max 5MB images
        }

        if ($this->input('status') === 'cancelled') {
            $rules['comment'] = ['required', 'string', 'min:5'];
        } else {
            $rules['comment'] = ['nullable', 'string'];
        }

        return $rules;
    }
    
    public function messages(): array
    {
        return [
            'resolution_note.required' => 'Catatan solusi perbaikan wajib diisi saat menyelesaikan tiket.',
            'resolution_proofs.*.max' => 'Ukuran gambar bukti perbaikan tidak boleh melebihi 5 MB.',
            'resolution_proofs.*.mimes' => 'Format berkas tidak didukung. Harap unggah berkas gambar (JPG, JPEG, PNG).',
            'comment.required' => 'Alasan wajib diisi saat membatalkan tiket.',
        ];
    }
}
