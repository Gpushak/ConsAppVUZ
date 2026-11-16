@extends('layouts.app')

@section('title', 'Мои слоты')

@section('content')
  <div class="page-head">
    <h1>Мои слоты</h1>
    <div class="actions">
      @if ($showFinishedToggle)
        <button class="btn btn-ghost" type="button" data-finished-toggle aria-pressed="false">Показать завершённые</button>
      @endif
      <a class="btn" href="{{ route('teacher.slots.create') }}">Новый слот</a>
    </div>
  </div>
  @include('partials.errors')
  @include('partials.week-range', ['span' => $span, 'action' => route('teacher.slots.index')])
  @if ($slots->isEmpty())
    <p class="notice">Слотов пока нет. <a href="{{ route('teacher.slots.create') }}">Создайте первый слот</a>.</p>
  @endif
  <div class="week">
    @foreach ($days as $day)
      <section class="day" aria-labelledby="day-{{ $day['date']->format('Ymd') }}">
        @include('partials.day-head', ['date' => $day['date']])
        @forelse ($day['slots'] as $slot)
          @php
            $showStop = $stopSlotIds->contains($slot->id);
            $showMark = $slot->series_id !== null && ! $showStop;
            if ($slot->canBeConducted()) {
                $primary = 'conduct';
            } elseif ($slot->canBeEdited()) {
                $primary = 'edit';
            } elseif ($slot->canBeUnpublished()) {
                $primary = 'unpublish';
            } elseif ($showStop) {
                $primary = 'stop';
            } else {
                $primary = null;
            }
            $hasMore = ($slot->canBeConducted() && $primary !== 'conduct')
                || ($slot->canBeEdited() && $primary !== 'edit')
                || ($slot->canBeUnpublished() && $primary !== 'unpublish')
                || ($showStop && $primary !== 'stop');
            $ahead = $seriesAhead->get($slot->id);
          @endphp
          <article @class(['slot', 'done' => $slot->isFinished()]) data-slot-card @if ($slot->isFinished()) data-finished hidden @endif>
            <button class="slot-toggle" type="button" aria-expanded="false" aria-controls="slot-details-{{ $slot->id }}">
              <span class="time">
                <span class="slot-date-text">{{ $slot->dateLabel() }}</span>
                <span class="slot-time-text">{{ $slot->timeRange() }}</span>
              </span>
              <span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span>
            </button>
            <div class="slot-details" id="slot-details-{{ $slot->id }}" hidden>
              @if ($showMark)
                <span class="series-mark">еженедельно@if ($ahead), {{ \App\Support\Ru::aheadDays($ahead) }}@endif</span>
              @elseif ($ahead)
                <span class="series-mark">{{ \App\Support\Ru::aheadDays($ahead) }}</span>
              @endif
              @include('partials.slot-place', ['slot' => $slot])
              @if ($slot->comment)
                <p class="comment">{{ $slot->comment }}</p>
              @endif
              @include('partials.signup-list', ['slot' => $slot, 'asCell' => false])
              @if ($primary)
                <div class="actions slot-primary">
                  @if ($primary === 'conduct')
                    <a class="btn btn-small" href="{{ route('teacher.slots.conduct', $slot) }}">Отметить проведённой</a>
                  @elseif ($primary === 'edit')
                    <a class="btn btn-small" href="{{ route('teacher.slots.edit', $slot) }}">Изменить</a>
                  @elseif ($primary === 'unpublish')
                    <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.unpublish.confirm', $slot) }}">Снять с публикации</a>
                  @else
                    <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.series.stop', $slot) }}">Остановить повтор</a>
                  @endif
                </div>
              @endif
              @if ($hasMore)
                <div class="actions slot-more">
                  @if ($slot->canBeConducted() && $primary !== 'conduct')
                    <a class="btn btn-small" href="{{ route('teacher.slots.conduct', $slot) }}">Отметить проведённой</a>
                  @endif
                  @if ($slot->canBeEdited() && $primary !== 'edit')
                    <a class="btn btn-small" href="{{ route('teacher.slots.edit', $slot) }}">Изменить</a>
                  @endif
                  @if ($slot->canBeUnpublished() && $primary !== 'unpublish')
                    <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.unpublish.confirm', $slot) }}">Снять с публикации</a>
                  @endif
                  @if ($showStop && $primary !== 'stop')
                    <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.series.stop', $slot) }}">Остановить повтор</a>
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
@endsection
