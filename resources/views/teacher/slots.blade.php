@extends('layouts.app')

@section('title', 'Мои слоты')

@section('content')
  <div class="page-head">
    <h1>Мои слоты</h1>
    <div class="actions">
      @if ($slots->contains(fn ($slot) => $slot->isFinished()))
        <button class="btn btn-ghost" type="button" data-finished-toggle aria-pressed="false">Показать завершённые</button>
      @endif
      <a class="btn" href="{{ route('teacher.slots.create') }}">Новый слот</a>
    </div>
  </div>
  @include('partials.errors')
  @if ($slots->isEmpty())
    <p class="notice">Слотов пока нет. <a href="{{ route('teacher.slots.create') }}">Создайте первый слот</a>.</p>
  @else
  <div class="table-wrap slot-cards">
    <table class="data-table slot-table">
      <thead>
        <tr>
          <th>Дата</th>
          <th>Время</th>
          <th>Место</th>
          <th>Студенты</th>
          <th>Комментарий</th>
          <th>Статус</th>
          <th data-skip>Действие</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($slots as $slot)
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
          @endphp
          @php
            $ahead = $seriesAhead->get($slot->id);
          @endphp
          <tr class="slot-row" @if ($slot->isFinished()) data-finished hidden @endif>
            <td class="slot-date">
              {{ $slot->dateLabel() }}
              @if ($showMark)
                <span class="series-mark">еженедельно@if ($ahead), {{ \App\Support\Ru::aheadDays($ahead) }}@endif</span>
              @elseif ($ahead)
                <span class="series-mark">{{ \App\Support\Ru::aheadDays($ahead) }}</span>
              @endif
            </td>
            <td class="slot-time">{{ $slot->timeRange() }}</td>
            <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
            @include('partials.signup-list', ['slot' => $slot])
            <td @class(['soft', 'slot-comment', 'is-empty' => ! $slot->comment])>{{ $slot->comment ?: '—' }}</td>
            <td class="slot-status"><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
            <td class="fit slot-actions">
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
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
@endsection
