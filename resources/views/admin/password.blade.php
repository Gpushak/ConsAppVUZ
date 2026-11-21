@extends('layouts.app')

@section('title', 'Новый пароль')

@section('content')
  <div class="page-head">
    <h1>Новый пароль</h1>
  </div>
  @include('partials.errors', ['inlineErrors' => ['password']])
  <p class="account-line"><strong>{{ $user->name }}</strong>, {{ $user->email }}, {{ $user->role->name }}.</p>
  <p class="notice">Открытая сессия прервётся. При следующем входе пароль нужно сменить. Передайте временный пароль лично: письма нет.</p>
  <form method="POST" action="{{ route('admin.users.password', $user) }}" novalidate>
    @csrf
    <div class="field">
      <label class="visually-hidden" for="password">Временный пароль</label>
      <input id="password" type="text" name="password" value="{{ old('password') }}" placeholder="Временный пароль" autocomplete="off" @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
      <p class="field-note">Не короче 8 символов.</p>
      @error('password')
        <p class="error" id="password-error">{{ $message }}</p>
      @enderror
    </div>
    <p class="form-actions"><button class="btn" type="submit">Задать временный пароль</button></p>
  </form>
  <p class="hint"><a href="{{ route('admin.users.index') }}">К пользователям</a></p>
@endsection
