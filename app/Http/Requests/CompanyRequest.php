<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $user = $this->user();
    
        return [
            'name' => [
                'required',
                Rule::unique('companies')->ignore($this->header('company'), 'id'),
            ],
            'vat_id' => ['nullable'],
            'tax_id' => ['nullable'],
            'slug'   => ['nullable'],
    
            // NUEVOS CAMPOS
            'contact_email' => ['required','email'],   // obligatorio
            'contact_name'  => ['nullable','string','max:255'],
            'website'       => ['nullable','string','max:255'],
    
            // País: solo obligatorio para 'asistencia'; para el resto, nullable
            'address.country_id' => [
                $user && $user->role === 'asistencia' ? 'required' : 'nullable',
            ],
        ];
    }
    
    public function getCompanyPayload()
    {
        return collect($this->validated())
            ->only([
                'name',
                'slug',
                'vat_id',
                'tax_id',
                // incluir los nuevos
                'contact_email',
                'contact_name',
                'website',
            ])
            ->toArray();
    }
}
