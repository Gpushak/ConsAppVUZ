@extends('layouts.app')

@section('title', 'Слоты избранных')

@section('content')
  <div class="page-head">
    <h1>Слоты избранных</h1>
  </div>
  @include('partials.errors')
  @if ($favorites->isEmpty())
    <p class="notice">Избранных преподавателей нет. <a href="{{ route('teachers.index') }}">Откройте преподавателей</a> и отметьте, чьи слоты смотреть.</p>
  @else
    <section class="near" aria-labelledby="near-title">
      <h2 id="near-title">Ближайшая консультация</h2>
      <div class="near-main">
        <form class="near-form" method="get" action="{{ route('home') }}">
          <input type="hidden" name="from" value="{{ $span->from->toDateString() }}">
          <input type="hidden" name="to" value="{{ $span->query()['to'] }}">
          <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
          <label class="field">
            <span>Преподаватель</span>
            <select name="teacher" onchange="this.form.submit()">
              <option value="" @selected($selectedTeacher === null)>не выбран</option>
              @foreach ($favorites as $favorite)
                <option value="{{ $favorite->id }}" @selected($selectedTeacher !== null && $selectedTeacher->id === $favorite->id)>{{ $favorite->user->name }}</option>
              @endforeach
            </select>
          </label>
          <button class="btn" type="submit">Показать</button>
        </form>
        @if ($selectedTeacher === null)
          <p class="nearest-empty">Выберите преподавателя.</p>
        @elseif ($nearest === null)
          <p class="nearest-empty">Ближайших консультаций нет.</p>
        @else
          <section class="nearest" data-nearest="{{ $nearest->id }}">
            <p class="nearest-when">{{ $nearest->dateLabel() }}, {{ $nearest->timeRange() }}</p>
            @include('partials.slot-place', ['slot' => $nearest])
            <p class="nearest-status"><span class="chip {{ $nearest->chipClass() }}">{{ $nearest->statusLabel() }}</span></p>
          </section>
        @endif
      </div>
      <div class="near-cal">
        <div class="cal-nav">
          <a class="cal-arrow" href="{{ route('home') }}?{{ http_build_query($monthLinks['previous']) }}" aria-label="Предыдущий месяц" rel="prev">‹</a>
          <span id="cal-title" class="cal-title">{{ $monthTitle }}</span>
          <a class="cal-arrow" href="{{ route('home') }}?{{ http_build_query($monthLinks['next']) }}" aria-label="Следующий месяц" rel="next">›</a>
        </div>
        <table class="month-cal" aria-labelledby="cal-title">
          <thead>
            <tr>
              @foreach (['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $dow)
                <th scope="col">{{ $dow }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach ($weeks as $week)
              <tr>
                @foreach ($week as $day)
                  <td @class(['cal-day', 'marked' => $day['marked'], 'out' => ! $day['inMonth'], 'today' => $day['today']]) data-date="{{ $day['date']->toDateString() }}" @if ($day['marked']) title="есть консультация" @endif>
                    <span>{{ $day['date']->day }}</span>
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
    <form class="week-range" method="get" action="{{ route('home') }}">
      @if ($selectedTeacher)
        <input type="hidden" name="teacher" value="{{ $selectedTeacher->id }}">
      @endif
      <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
      <label class="field">
        <span>С</span>
        <input type="date" name="from" value="{{ $span->from->toDateString() }}">
      </label>
      <label class="field">
        <span>По</span>
        <input type="date" name="to" value="{{ $span->query()['to'] }}">
      </label>
      <button class="btn" type="submit">Показать</button>
      <a class="btn btn-ghost" href="{{ route('home') }}?{{ http_build_query($weekLinks['previous']) }}">Предыдущая неделя</a>
      <a class="btn btn-ghost" href="{{ route('home') }}?{{ http_build_query($weekLinks['next']) }}">Следующая неделя</a>
    </form>
    @if ($span->error)
      <p class="notice error">{{ $span->error }}</p>
    @endif
    @unless ($hasOpen)
      <p class="notice">Свободных слотов нет. <a href="{{ route('teachers.index') }}">Откройте преподавателей</a>, чтобы выбрать, чьи слоты смотреть.</p>
    @endunless
    <div>
      <div class="week">
        @foreach ($days as $day)
          <section class="day" aria-labelledby="day-{{ $day['date']->format('Ymd') }}">
            @include('partials.day-head', ['date' => $day['date']])
            @forelse ($day['slots'] as $slot)
              @php
                $mine = $slot->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
              @endphp
              <article @class(['slot', 'mine' => (bool) $mine]) data-slot-card>
                <button class="slot-toggle" type="button" aria-expanded="false" aria-controls="slot-details-{{ $slot->id }}">
                  <span class="time">
                    <span class="slot-date-text">{{ $slot->dateLabel() }}</span>
                    <span class="slot-time-text">{{ $slot->timeRange() }}</span>
                  </span>
                  <span class="who-line">{{ $slot->teacherProfile->user->name }}</span>
                  <span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span>
                </button>
                <div class="slot-details" id="slot-details-{{ $slot->id }}" hidden>
                  <p class="fact">{{ $slot->teacherProfile->position }}, {{ $slot->teacherProfile->department }}</p>
                  @include('partials.slot-place', ['slot' => $slot])
                  @if ($slot->comment)
                    <p class="comment">{{ $slot->comment }}</p>
                  @endif
                  @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true, 'asCell' => false])
                  @if ($mine || $slot->canBeBooked())
                    <div class="actions slot-primary">
                      @if ($mine)
                        <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
                      @else
                        <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}">Записаться</a>
                      @endif
                    </div>
                  @endif
                </div>
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
