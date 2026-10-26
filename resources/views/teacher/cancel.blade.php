@extends('layouts.app')

@section('title', 'Отмена записи')

@section('content')
  <div class="page-head">
    <div>
      <h1>Отмена записи</h1>
      <p class="lead">{{ $booking->studentProfile->user->name }}, {{ $booking->studentProfile->group_name }}, {{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }}, @include('partials.slot-place', ['slot' => $booking->slot]).</p>
    </div>
  </div>
  @include('partials.errors')
  <p class="notice">Отменяется запись одного студента. Остальные остаются, новая запись на слот возможна.</p>
  <form class="panel" method="POST" action="{{ route('teacher.bookings.cancel.store', $booking) }}">
    @csrf
    <div class="field">
      <label for="comment">Комментарий, необязательно</label>
      <textarea id="comment" name="comment" maxlength="500">{{ old('comment') }}</textarea>
    </div>
    <p class="form-actions"><button class="btn" type="submit">Отменить запись</button></p>
  </form>
  <p class="hint"><a href="{{ route('teacher.slots.index') }}">К моим слотам</a></p>
@endsection
