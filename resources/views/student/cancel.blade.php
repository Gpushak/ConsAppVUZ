@extends('layouts.app')

@section('title', 'Отмена записи')

@section('content')
  <div class="page-head">
    <h1>Отмена записи</h1>
  </div>
  @include('partials.errors')
  <div class="panel slot-panel confirm-panel">
    <p class="confirm-who">{{ $booking->slot->teacherProfile->user->name }}</p>
    <p>{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }}</p>
    @include('partials.slot-place', ['slot' => $booking->slot])
    <p><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></p>
  </div>
  <p class="notice">Отмена сохраняется. Слот для остальных студентов остаётся открытым.</p>
  <form method="POST" action="{{ route('bookings.cancel.store', $booking) }}">
    @csrf
    <p class="form-actions confirm-actions">
      <a class="btn btn-ghost" href="{{ route('bookings.index') }}">Не отменять</a>
      <button class="btn btn-danger" type="submit">Отменить запись</button>
    </p>
  </form>
@endsection
