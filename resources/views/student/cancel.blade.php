@extends('layouts.app')

@section('title', 'Отмена записи')

@section('content')
  <div class="page-head">
    <h1>Отмена записи</h1>
  </div>
  @include('partials.errors')
  <p class="notice">Отмена сохраняется. Слот для остальных студентов остаётся открытым.</p>
  <form method="POST" action="{{ route('bookings.cancel.store', $booking) }}">
    @csrf
    <p class="form-actions"><button class="btn" type="submit">Отменить запись</button></p>
  </form>
  <p class="hint"><a href="{{ route('bookings.index') }}">Назад к записям</a></p>
@endsection
