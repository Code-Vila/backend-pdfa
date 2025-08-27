<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpansionRequest extends FormRequest
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
        return [
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'justification' => 'required|string|min:50|max:1000',
            'requested_limit' => 'required|integer|min:' . (config('pdfa.default_daily_limit') + 1) . '|max:10000',
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
            'email.required' => 'E-mail é obrigatório.',
            'email.email' => 'E-mail deve ser válido.',
            'email.max' => 'E-mail deve ter no máximo 255 caracteres.',
            'name.required' => 'Nome é obrigatório.',
            'name.string' => 'Nome deve ser um texto válido.',
            'name.max' => 'Nome deve ter no máximo 255 caracteres.',
            'company.string' => 'Empresa deve ser um texto válido.',
            'company.max' => 'Empresa deve ter no máximo 255 caracteres.',
            'justification.required' => 'Justificativa é obrigatória.',
            'justification.string' => 'Justificativa deve ser um texto válido.',
            'justification.min' => 'Justificativa deve ter pelo menos 50 caracteres.',
            'justification.max' => 'Justificativa deve ter no máximo 1000 caracteres.',
            'requested_limit.required' => 'Limite solicitado é obrigatório.',
            'requested_limit.integer' => 'Limite deve ser um número inteiro.',
            'requested_limit.min' => 'Limite deve ser maior que o limite padrão (' . config('pdfa.default_daily_limit') . ').',
            'requested_limit.max' => 'Limite máximo é 10.000 conversões por dia.',
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
            'email' => 'e-mail',
            'name' => 'nome',
            'company' => 'empresa',
            'justification' => 'justificativa',
            'requested_limit' => 'limite solicitado',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'name' => trim($this->name),
            'company' => $this->company ? trim($this->company) : null,
            'justification' => trim($this->justification),
        ]);
    }
}
