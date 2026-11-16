@extends('layouts.app')

@section('title', 'Запись на слот')

@section('content')
  <div class="page-head">
    <h1>Подтверждение записи</h1>
  </div>
  @include('partials.errors', ['inlineErrors' => ['slot']])
  <div class="panel slot-panel">
    <p>{{ $slot->teacherProfile->user->name }}, {{ $slot->teacherProfile->position }}</p>
    <p class="meta">{{ $slot->teacherProfile->department }}</p>
    <p>{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}</p>
    @include('partials.slot-place', ['slot' => $slot])
    @if ($slot->comment)
      <p>{{ $slot->comment }}</p>
    @endif
    <p><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></p>
    @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true, 'open' => true, 'inline' => true, 'asCell' => false])
  </div>
  @if ($mine)
    <p class="notice">Вы уже записаны на этот слот.</p>
    <p class="hint"><a href="{{ route('bookings.show', $mine) }}">Открыть запись</a></p>
  @elseif ($slot->canBeBooked())
    <form method="POST" action="{{ route('bookings.store', $slot) }}">
      @csrf
      <div class="field">
        @error('slot')
          <p class="error" id="slot-error">{{ $message }}</p>
        @enderror
        <p class="form-actions"><button class="btn" type="submit">Записаться</button></p>
      </div>
    </form>
  @elseif (! $slot->isFinished())
    <p class="notice">На этот слот уже нельзя записаться.</p>
  @endif
  <p class="hint"><a href="{{ route('home') }}">К слотам избранных</a></p>
@endsection
