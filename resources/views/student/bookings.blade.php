@extends('layouts.app')

@section('title', 'Мои записи')

@section('content')
  <div class="page-head">
    <h1>Мои записи</h1>
    @if ($bookings->contains(fn ($booking) => $booking->isFinished()))
      <button class="btn btn-ghost" type="button" data-finished-toggle aria-pressed="false">Показать завершённые</button>
    @endif
  </div>
  @include('partials.errors')
  @if ($bookings->isEmpty())
    <p class="notice">Записей пока нет. <a href="{{ route('home') }}">Посмотрите слоты избранных</a>.</p>
  @else
    <div class="table-wrap slot-cards booking-cards">
      <table class="data-table slot-table">
        <thead>
          <tr>
            <th>Дата</th>
            <th>Время</th>
            <th>Преподаватель</th>
            <th>Место</th>
            <th>Комментарий</th>
            <th>Статус</th>
            <th data-skip>Действие</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($bookings as $booking)
            @php
              $comment = trim((string) $booking->slot->comment.' '.(string) $booking->teacher_comment);
            @endphp
            <tr class="slot-row booking-card" @if ($booking->isFinished()) data-finished hidden @endif>
              <td class="slot-date booking-date"><span class="cell-label">Дата</span>{{ $booking->slot->dateLabel() }}</td>
              <td class="slot-time booking-time"><span class="cell-label">Время</span>{{ $booking->slot->timeRange() }}</td>
              <td class="slot-who booking-who">
                <span class="cell-label">Преподаватель</span>
                {{ $booking->slot->teacherProfile->user->name }}
              </td>
              <td class="place booking-place">@include('partials.slot-place', ['slot' => $booking->slot])</td>
              <td @class(['soft', 'slot-comment', 'booking-note', 'is-empty' => $comment === ''])>
                @if ($booking->slot->comment)
                  <div class="note-line"><span class="note-label">К слоту</span>{{ $booking->slot->comment }}</div>
                @endif
                @if ($booking->teacher_comment)
                  <div class="note-line"><span class="note-label">От преподавателя</span>{{ $booking->teacher_comment }}</div>
                @endif
                @if ($comment === '')
                  —
                @endif
              </td>
              <td class="slot-status booking-status">
                <span class="cell-label">Статус</span>
                <span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span>
              </td>
              <td class="slot-actions booking-actions">
                @can('cancel', $booking)
                  <div class="actions slot-primary booking-primary">
                    <a class="btn btn-small btn-ghost btn-quiet" href="{{ route('bookings.cancel', $booking) }}" aria-label="Отменить запись: {{ $booking->slot->teacherProfile->user->name }}, {{ $booking->slot->dateLabel() }}">Отменить</a>
                  </div>
                @endcan
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
