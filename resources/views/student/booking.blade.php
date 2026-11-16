@extends('layouts.app')

@section('title', 'Запись')

@section('content')
  <div class="page-head">
    <h1>Запись</h1>
  </div>
  @include('partials.errors')
  <div class="panel slot-panel">
    <p>{{ $booking->slot->teacherProfile->user->name }}</p>
    <p>{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }}</p>
    @include('partials.slot-place', ['slot' => $booking->slot])
    @if ($booking->slot->comment)
      <p>{{ $booking->slot->comment }}</p>
    @endif
    @if ($booking->teacher_comment)
      <p>{{ $booking->teacher_comment }}</p>
    @endif
    <p><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></p>
  </div>
  @can('cancel', $booking)
    <p class="hint"><a href="{{ route('bookings.cancel', $booking) }}">Отменить запись</a></p>
  @endcan
  <p class="hint"><a href="{{ route('bookings.index') }}">Мои записи</a></p>
@endsection
