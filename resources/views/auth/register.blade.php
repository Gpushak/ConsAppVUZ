@extends('layouts.app')

@section('title', 'Регистрация студента')

@section('content')
  @php
    $mismatch = 'Пароль и повтор не совпадают.';
    $passwordInvalid = collect($errors->get('password'))->contains(fn ($message) => $message !== $mismatch);
    $confirmInvalid = collect($errors->get('password'))->contains($mismatch) || $errors->has('password_confirmation');
  @endphp
  <div class="auth">
    <h1>Регистрация студента</h1>
    <p class="lead">После регистрации открывается кабинет студента.</p>
    @include('partials.errors', ['messageIds' => [$mismatch => 'password_confirmation-error']])
    <form method="POST" action="{{ route('register.store') }}">
      @csrf
      <div class="field">
        <label for="name">ФИО</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
      </div>
      <div class="field">
        <label for="group_name">Группа</label>
        <input id="group_name" type="text" name="group_name" value="{{ old('group_name') }}" required @if ($errors->has('group_name')) aria-invalid="true" aria-describedby="group_name-error" @endif>
      </div>
      <div class="field">
        <label for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
      </div>
      <div class="field">
        <label for="password">Пароль</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required @if ($passwordInvalid) aria-invalid="true" aria-describedby="password-error" @endif>
      </div>
      <div class="field">
        <label for="password_confirmation">Повтор пароля</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required @if ($confirmInvalid) aria-invalid="true" aria-describedby="password_confirmation-error" @endif>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Зарегистрироваться</button></p>
    </form>
    <p class="hint"><a href="{{ route('login') }}">Вход</a></p>
  </div>
@endsection
