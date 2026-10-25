<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'position' => trim((string) $this->input('position')),
            'department' => trim((string) $this->input('department')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите ФИО.',
            'email.required' => 'Укажите почту.',
            'email.email' => 'Почта указана неверно.',
            'email.unique' => 'Эта почта уже занята.',
            'position.required' => 'Укажите должность.',
            'department.required' => 'Укажите кафедру.',
            'password.required' => 'Укажите временный пароль.',
            'password.min' => 'Пароль не короче 8 символов.',
        ];
    }
}
