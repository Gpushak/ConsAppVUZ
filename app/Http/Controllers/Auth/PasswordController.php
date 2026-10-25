<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.password', [
            'forced' => $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if (! $request->user()->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $request->validate($rules, [
            'current_password.required' => 'Укажите текущий пароль.',
            'current_password.current_password' => 'Текущий пароль неверный.',
            'password.required' => 'Укажите новый пароль.',
            'password.min' => 'Пароль не короче 8 символов.',
            'password.confirmed' => 'Пароль и повтор не совпадают.',
        ]);

        $request->user()->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Пароль изменён.');
    }
}
