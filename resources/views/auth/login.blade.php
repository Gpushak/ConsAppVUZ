@extends('layouts.app')

@section('title', 'Вход')

@section('content')
  <div class="auth auth-centered">
    <h1>Вход</h1>
    @include('partials.errors')
    <form method="POST" action="{{ route('login.store') }}">
      @csrf
      <div class="field">
        <label class="visually-hidden" for="email">Почта</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Почта" autocomplete="username" required @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
      </div>
      <div class="field">
        <label class="visually-hidden" for="password">Пароль</label>
        <input id="password" type="password" name="password" placeholder="Пароль" autocomplete="current-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Войти</button></p>
    </form>
    <a class="btn btn-ghost auth-next" href="{{ route('register') }}">Регистрация студента</a>
  </div>
@endsection
