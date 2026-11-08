@extends('layouts.app')

@section('title', 'Записавшиеся')

@section('content')
  <div class="page-head">
    <h1>Записавшиеся</h1>
  </div>
  <p class="meta">{{ $slot->dateLabel() }}, {{ $slot->timeRange() }}</p>
  <p class="place">@include('partials.slot-place', ['slot' => $slot])</p>
  @include('partials.errors')
  @if ($signups->isEmpty())
    <p class="notice">Записей пока нет</p>
  @else
    <div class="table-wrap">
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
            <tr>
              <td>{{ $booking->studentProfile->group_name }}</td>
              <td>
                {{ $booking->studentProfile->user->name }}
                @if (filled($booking->teacher_comment))
                  <span class="muted">{{ $booking->teacher_comment }}</span>
                @endif
              </td>
              <td><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></td>
              @if ($teacherActions)
                <td class="fit">
                  @if ($booking->isActive())
                    <a class="btn btn-small btn-ghost" href="{{ route('teacher.bookings.cancel', $booking) }}">Отменить</a>
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
