@extends('layouts.app')

@section('title', 'Смена пароля')

@section('content')
  <div class="auth">
    <h1>{{ $forced ? 'Новый пароль' : 'Смена пароля' }}</h1>
    <p class="lead">
      {{ $forced ? 'Временный пароль нужно сменить до входа в кабинет.' : 'Пароль не короче 8 символов.' }}
    </p>
    @include('partials.errors')
    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      @unless ($forced)
        <div class="field">
          <label for="current_password">Текущий пароль</label>
          <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
        </div>
      @endunless
      <div class="field">
        <label for="password">Новый пароль</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
      </div>
      <div class="field">
        <label for="password_confirmation">Повтор пароля</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
      </div>
      <p class="form-actions"><button class="btn" type="submit">Сохранить пароль</button></p>
    </form>
  </div>
@endsection
