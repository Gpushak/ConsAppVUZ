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
        $kind = $this->input('kind');

        $this->merge([
            'kind' => is_string($kind) && $kind !== '' ? $kind : 'teacher',
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'position' => trim((string) $this->input('position')),
            'department' => trim((string) $this->input('department')),
            'group_name' => trim((string) $this->input('group_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'kind' => ['required', 'in:teacher,student'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];

        if ($this->input('kind') === 'student') {
            $rules['group_name'] = ['required', 'string', 'max:32'];

            return $rules;
        }

        $rules['position'] = ['required', 'string', 'max:255'];
        $rules['department'] = ['required', 'string', 'max:255'];

        return $rules;
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
            'group_name.required' => 'Укажите учебную группу.',
            'password.required' => 'Укажите временный пароль.',
            'password.min' => 'Пароль не короче 8 символов.',
            'name.max' => 'ФИО не длиннее 255 символов.',
            'email.max' => 'Почта не длиннее 255 символов.',
            'position.max' => 'Должность не длиннее 255 символов.',
            'department.max' => 'Кафедра не длиннее 255 символов.',
            'group_name.max' => 'Учебная группа не длиннее 32 символов.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.users.index');
    }
}
