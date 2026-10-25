@extends('layouts.app')

@section('title', 'Вход')

@section('content')
  <div class="auth">
    <h1>Вход</h1>
    <p class="lead">Почта и пароль учётной записи.</p>
    @include('partials.errors')
    <form method="POST" action="{{ route('login.store') }}">
      @csrf
      <div class="field">
        <label for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="password">Пароль</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Войти</button></p>
    </form>
    <p class="hint"><a href="{{ route('register') }}">Регистрация студента</a></p>
  </div>
@endsection
