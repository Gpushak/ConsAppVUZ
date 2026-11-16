@extends('layouts.app')

@section('title', 'Разблокировка')

@section('content')
  <div class="page-head">
    <h1>Разблокировать пользователя</h1>
  </div>
  @include('partials.errors')
  <p class="account-line"><strong>{{ $user->name }}</strong>, {{ $user->email }}, {{ $user->role->name }}.</p>
  <p class="notice">Вход снова станет доступен. Открытая сессия прервётся, войти можно заново.</p>
  <form method="POST" action="{{ route('admin.users.unblock', $user) }}">
    @csrf
    <p class="form-actions"><button class="btn" type="submit">Разблокировать</button></p>
  </form>
  <p class="hint"><a href="{{ route('admin.users.index') }}">К пользователям</a></p>
@endsection
