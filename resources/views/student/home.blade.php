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
      <div class="near-head">
        <h2 id="near-title">
          @if ($activeDay)
            <span class="near-dow">{{ \App\Support\DateSpan::weekday($activeDay) }}</span>
            {{ $activeDay->format('d.m.Y') }}
          @else
            Ближайшая консультация
          @endif
        </h2>
        @if ($activeDay)
          <a class="btn btn-ghost btn-small" href="{{ route('home') }}?{{ http_build_query($nearestQuery) }}">Ближайшая</a>
        @endif
      </div>
      <div class="near-main">
        <form class="near-form" method="get" action="{{ route('home') }}">
          <input type="hidden" name="from" value="{{ $span->from->toDateString() }}">
          <input type="hidden" name="to" value="{{ $span->query()['to'] }}">
          <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
          @if ($activeDay)
            <input type="hidden" name="day" value="{{ $activeDay->toDateString() }}">
          @endif
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
        @if ($daySlots !== null)
          <section class="near-day" data-day="{{ $activeDay->toDateString() }}">
            @foreach ($daySlots as $slot)
              @php
                $mine = $slot->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
              @endphp
              <article class="day-row" data-day-slot="{{ $slot->id }}">
                <div class="day-row-top">
                  <p class="nearest-when">{{ $slot->timeRange() }}</p>
                  <span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span>
                </div>
                <p class="day-row-who">{{ $slot->teacherProfile->user->name }}</p>
                @include('partials.slot-place', ['slot' => $slot])
                @include('partials.slot-book', ['slot' => $slot, 'mine' => $mine])
              </article>
            @endforeach
          </section>
        @elseif ($selectedTeacher === null)
          <p class="nearest-empty">Выберите преподавателя.</p>
        @elseif ($nearest === null)
          <p class="nearest-empty">Ближайших консультаций нет.</p>
        @else
          @php
            $mine = $nearest->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
          @endphp
          <section class="nearest" data-nearest="{{ $nearest->id }}">
            <p class="nearest-when">{{ $nearest->dateLabel() }}, {{ $nearest->timeRange() }}</p>
            @include('partials.slot-place', ['slot' => $nearest])
            <p class="nearest-status"><span class="chip {{ $nearest->chipClass() }}">{{ $nearest->statusLabel() }}</span></p>
            @include('partials.slot-book', ['slot' => $nearest, 'mine' => $mine])
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
                  @php
                    $dateKey = $day['date']->toDateString();
                    $chosen = $activeDay !== null && $activeDay->toDateString() === $dateKey;
                    $dayLink = $nearestQuery;
                    if (! $chosen) {
                        $dayLink['day'] = $dateKey;
                    }
                  @endphp
                  <td @class(['cal-day', 'marked' => $day['marked'], 'chosen' => $chosen, 'out' => ! $day['inMonth'], 'today' => $day['today']]) data-date="{{ $dateKey }}" @if ($day['marked']) title="есть консультация" @endif>
                    @if ($day['marked'])
                      <a href="{{ route('home') }}?{{ http_build_query($dayLink) }}">{{ $day['date']->day }}</a>
                    @else
                      <span>{{ $day['date']->day }}</span>
                    @endif
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
      @if ($activeDay)
        <input type="hidden" name="day" value="{{ $activeDay->toDateString() }}">
      @endif
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
                  @include('partials.slot-book', ['slot' => $slot, 'mine' => $mine])
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
