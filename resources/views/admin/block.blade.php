@extends('layouts.app')

@section('title', 'Блокировка')

@section('content')
  <div class="page-head">
    <h1>Заблокировать пользователя</h1>
  </div>
  @include('partials.errors')
  <p class="notice">Вход станет недоступен, открытая сессия прервётся.</p>
  <form method="POST" action="{{ route('admin.users.block', $user) }}">
    @csrf
    <p class="form-actions"><button class="btn" type="submit">Заблокировать</button></p>
  </form>
  <p class="hint"><a href="{{ route('admin.users.index') }}">К пользователям</a></p>
@endsection
