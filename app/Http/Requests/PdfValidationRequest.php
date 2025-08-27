<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PdfValidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Para validação única
        if ($this->route()->getName() === 'api.validate.pdf' || $this->route()->getName() === 'api.validate.pdf-a') {
            return [
                'file' => 'required|file|mimes:pdf|max:' . (config('pdfa.max_file_size', 10240)),
            ];
        }

        // Para estimativa de múltiplos arquivos
        return [
            'files' => 'required|array|max:5',
            'files.*' => 'required|file|mimes:pdf|max:' . (config('pdfa.max_file_size', 10240)),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Arquivo PDF é obrigatório.',
            'file.file' => 'Deve ser um arquivo válido.',
            'file.mimes' => 'Apenas arquivos PDF são aceitos.',
            'file.max' => 'Arquivo muito grande. Máximo: ' . config('pdfa.max_file_size', 10240) . 'KB.',
            'files.required' => 'Pelo menos um arquivo PDF é obrigatório.',
            'files.array' => 'Files deve ser um array.',
            'files.max' => 'Máximo de 5 arquivos por vez.',
            'files.*.required' => 'Arquivo é obrigatório.',
            'files.*.file' => 'Deve ser um arquivo válido.',
            'files.*.mimes' => 'Apenas arquivos PDF são aceitos.',
            'files.*.max' => 'Arquivo muito grande. Máximo: ' . config('pdfa.max_file_size', 10240) . 'KB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'arquivo',
            'files' => 'arquivos',
            'files.*' => 'arquivo',
        ];
    }
}
