<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'group_name' => trim((string) $this->input('group_name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите ФИО.',
            'group_name.required' => 'Укажите учебную группу.',
            'email.required' => 'Укажите почту.',
            'email.email' => 'Почта указана неверно.',
            'email.unique' => 'Эта почта уже занята.',
            'password.required' => 'Укажите пароль.',
            'password.min' => 'Пароль не короче 8 символов.',
            'password.confirmed' => 'Пароль и повтор не совпадают.',
        ];
    }
}
