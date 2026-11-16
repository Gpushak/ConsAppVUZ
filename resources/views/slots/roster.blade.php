@extends('layouts.app')

@section('title', 'Записавшиеся')

@section('content')
  <div class="page-head">
    <h1>Записавшиеся</h1>
  </div>
  <p class="meta">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}</p>
  <div class="place">@include('partials.slot-place', ['slot' => $slot])</div>
  @include('partials.errors')
  @if ($signups->isEmpty())
    <p class="notice">На этот слот ещё никто не записался.</p>
  @else
    <div class="table-wrap roster-cards">
      <table class="data-table">
        <thead>
          <tr>
            <th>Группа</th>
            <th>Студент</th>
            <th>Статус</th>
            @if ($teacherActions)
              <th data-skip>Действие</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach ($signups as $booking)
            <tr class="roster-card">
              <td class="roster-group"><span class="cell-label">Группа</span>{{ $booking->studentProfile->group_name }}</td>
              <td class="roster-name">
                <span class="cell-label">Студент</span>
                {{ $booking->studentProfile->user->name }}
                @if (filled($booking->teacher_comment))
                  <span class="roster-note"><span class="note-label">Комментарий</span>{{ $booking->teacher_comment }}</span>
                @endif
              </td>
              <td class="roster-status">
                <span class="cell-label">Статус</span>
                <span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span>
              </td>
              @if ($teacherActions)
                <td class="roster-actions">
                  @if ($booking->isActive())
                    <a class="btn btn-small btn-ghost btn-quiet" href="{{ route('teacher.bookings.cancel', $booking) }}" aria-label="Отменить запись: {{ $booking->studentProfile->user->name }}, {{ $booking->studentProfile->group_name }}">Отменить</a>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
  <p class="hint">
    @if ($teacherActions)
      <a href="{{ route('teacher.slots.index') }}">К моим слотам</a>
    @else
      <a href="{{ route('home') }}">К слотам избранных</a>
    @endif
  </p>
@endsection
