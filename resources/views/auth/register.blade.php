@extends('layouts.app')

@section('title', 'Регистрация студента')

@section('content')
  <div class="auth">
    <h1>Регистрация студента</h1>
    <p class="lead">После регистрации открывается кабинет студента.</p>
    @include('partials.errors')
    <form method="POST" action="{{ route('register.store') }}">
      @csrf
      <div class="field">
        <label for="name">ФИО</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
      </div>
      <div class="field">
        <label for="group_name">Группа</label>
        <input id="group_name" type="text" name="group_name" value="{{ old('group_name') }}" required>
      </div>
      <div class="field">
        <label for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="password">Пароль</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
      </div>
      <div class="field">
        <label for="password_confirmation">Повтор пароля</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Зарегистрироваться</button></p>
    </form>
    <p class="hint"><a href="{{ route('login') }}">Вход</a></p>
  </div>
@endsection
