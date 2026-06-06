<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'store_id'              => ['required', 'uuid', 'exists:stores,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'     => 'Este e-mail já está em uso.',
            'store_id.exists'  => 'Loja não encontrada.',
            'password.min'     => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ];
    }
}
