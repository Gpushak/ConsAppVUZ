@extends('layouts.app')

@section('title', 'Слоты избранных')

@section('content')
  <div class="page-head">
    <h1>Слоты избранных</h1>
    @if ($showFinishedToggle)
      <button class="btn btn-ghost" type="button" data-finished-toggle aria-pressed="false">Показать завершённые</button>
    @endif
  </div>
  @include('partials.errors')
  @if ($favorites->isEmpty())
    <p class="notice">Избранных преподавателей нет. <a href="{{ route('teachers.index') }}">Откройте преподавателей</a> и отметьте, чьи слоты смотреть.</p>
  @else
    @include('partials.week-range', ['span' => $span, 'action' => route('home')])
    @unless ($hasOpen)
      <p class="notice">Свободных слотов нет. @if ($showFinishedToggle)Нажмите «Показать завершённые» или <a href="{{ route('teachers.index') }}">откройте преподавателей</a>.@else<a href="{{ route('teachers.index') }}">Откройте преподавателей</a>, чтобы выбрать, чьи слоты смотреть.@endif</p>
    @endunless
    @if ($onlyFinished)
    <div data-only-finished hidden>
    @else
    <div>
    @endif
      <div class="week">
        @foreach ($days as $day)
          <section class="day" aria-labelledby="day-{{ $day['date']->format('Ymd') }}">
            @include('partials.day-head', ['date' => $day['date']])
            @forelse ($day['slots'] as $slot)
              @php
                $mine = $slot->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
              @endphp
              <article @class(['slot', 'mine' => (bool) $mine, 'done' => $slot->isFinished()]) @if ($slot->isFinished()) data-finished hidden @endif>
                <p class="time">
                  <span class="slot-date-text">{{ $slot->dateLabel() }}</span>
                  <span class="slot-time-text">{{ $slot->timeRange() }}</span>
                </p>
                <p class="who-line">{{ $slot->teacherProfile->user->name }}</p>
                <p class="fact">{{ $slot->teacherProfile->position }}, {{ $slot->teacherProfile->department }}</p>
                @include('partials.slot-place', ['slot' => $slot])
                @if ($slot->comment)
                  <p class="comment">{{ $slot->comment }}</p>
                @endif
                @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true, 'asCell' => false])
                <span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span>
                @if ($mine || $slot->canBeBooked())
                  <div class="actions slot-primary">
                    @if ($mine)
                      <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
                    @else
                      <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}">Записаться</a>
                    @endif
                  </div>
                @endif
              </article>
            @empty
              <p class="empty">нет слотов</p>
            @endforelse
          </section>
        @endforeach
      </div>
    </div>
  @endif
@endsection
