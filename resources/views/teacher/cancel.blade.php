@extends('layouts.app')

@section('title', 'Отмена записи')

@section('content')
  <div class="page-head">
    <h1>Отмена записи</h1>
  </div>
  @include('partials.errors', ['inlineErrors' => ['comment']])
  <div class="panel slot-panel confirm-panel">
    <p class="confirm-who">{{ $booking->studentProfile->user->name }}, {{ $booking->studentProfile->group_name }}</p>
    <p>{{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }}</p>
    @include('partials.slot-place', ['slot' => $booking->slot])
    <p><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></p>
  </div>
  <p class="notice">Отменяется запись одного студента. Остальные остаются, новая запись на слот возможна.</p>
  <form class="panel slot-panel" method="POST" action="{{ route('teacher.bookings.cancel.store', $booking) }}">
    @csrf
    <div class="field">
      <label for="comment">Комментарий студенту, необязательно</label>
      <textarea id="comment" name="comment" maxlength="500" @if ($errors->has('comment')) aria-invalid="true" aria-describedby="comment-error" @endif>{{ old('comment') }}</textarea>
      @error('comment')
        <p class="error" id="comment-error">{{ $message }}</p>
      @enderror
      <p class="field-note">Студент увидит его в «Моих записях».</p>
    </div>
    <p class="form-actions confirm-actions">
      <a class="btn btn-ghost" href="{{ route('slots.roster', $booking->slot) }}">Не отменять</a>
      <button class="btn btn-danger" type="submit">Отменить запись</button>
    </p>
  </form>
@endsection
