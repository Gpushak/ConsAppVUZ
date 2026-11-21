@extends('layouts.app')

@section('title', 'Смена пароля')

@section('content')
  @php
    $mismatch = 'Пароль и повтор не совпадают.';
    $passwordInvalid = collect($errors->get('password'))->contains(fn ($message) => $message !== $mismatch);
    $confirmInvalid = collect($errors->get('password'))->contains($mismatch) || $errors->has('password_confirmation');
  @endphp
  <div class="auth auth-centered">
    <h1>{{ $forced ? 'Новый пароль' : 'Смена пароля' }}</h1>
    <p class="lead">
      {{ $forced ? 'Временный пароль нужно сменить до входа в кабинет.' : 'Пароль не короче 8 символов.' }}
    </p>
    @include('partials.errors', ['messageIds' => [$mismatch => 'password_confirmation-error']])
    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      @unless ($forced)
        <div class="field">
          <label class="visually-hidden" for="current_password">Текущий пароль</label>
          <input id="current_password" type="password" name="current_password" placeholder="Текущий пароль" autocomplete="current-password" required @if ($errors->has('current_password')) aria-invalid="true" aria-describedby="current_password-error" @endif>
        </div>
      @endunless
      <div class="field">
        <label class="visually-hidden" for="password">Новый пароль</label>
        <input id="password" type="password" name="password" placeholder="Новый пароль" autocomplete="new-password" required @if ($passwordInvalid) aria-invalid="true" aria-describedby="password-error" @endif>
      </div>
      <div class="field">
        <label class="visually-hidden" for="password_confirmation">Повтор пароля</label>
        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Повтор пароля" autocomplete="new-password" required @if ($confirmInvalid) aria-invalid="true" aria-describedby="password_confirmation-error" @endif>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Сохранить пароль</button></p>
    </form>
  </div>
@endsection
