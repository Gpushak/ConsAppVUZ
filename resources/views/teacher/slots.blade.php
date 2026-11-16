@extends('layouts.app')

@section('title', 'Мои слоты')

@section('content')
  <section class="next-slot" aria-labelledby="next-slot-title">
    <p class="next-slot-label" id="next-slot-title">Ближайшая консультация</p>
    @if ($nextSlot)
      <div class="nearest" data-next-slot="{{ $nextSlot->id }}">
        <p class="nearest-when">{{ $nextSlot->dateLabel() }}, {{ $nextSlot->timeRange() }}</p>
        @include('partials.slot-place', ['slot' => $nextSlot])
        <p class="nearest-status"><span class="chip {{ $nextSlot->chipClass() }}">{{ $nextSlot->statusLabel() }}</span></p>
        @include('partials.signup-list', ['slot' => $nextSlot, 'activeOnly' => true, 'asCell' => false])
        <div class="actions">
          @if ($nextSlot->canBeEdited())
            <a class="btn btn-small" href="{{ route('teacher.slots.edit', $nextSlot) }}">Изменить</a>
          @endif
          @if ($nextSlot->series_id !== null)
            <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.series.stop', $nextSlot) }}">Остановить повтор</a>
          @endif
        </div>
      </div>
    @else
      <p class="nearest-empty">Ближайших консультаций нет.</p>
    @endif
  </section>
  <div class="page-head">
    <h1>Мои слоты</h1>
    <div class="actions">
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
          <article class="slot" data-slot-card>
            <button class="slot-toggle" type="button" aria-expanded="false" aria-controls="slot-details-{{ $slot->id }}">
              <span class="time">
                <span class="slot-date-text">{{ $slot->dateLabel() }}</span>
                <span class="slot-time-text">{{ $slot->timeRange() }}</span>
              </span>
              <span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span>
            </button>
            <div class="slot-details" id="slot-details-{{ $slot->id }}" hidden>
              @if ($slot->series_id !== null)
                <span class="series-mark">{{ $slot->seriesStepLabel() }}@if ($ahead), {{ \App\Support\Ru::aheadDays($ahead) }}@endif</span>
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
