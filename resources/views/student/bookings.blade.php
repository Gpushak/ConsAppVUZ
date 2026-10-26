@extends('layouts.app')

@section('title', 'Мои записи')

@section('content')
  <div class="page-head">
    <div>
      <h1>Мои записи</h1>
      <p class="lead">Отменить можно только свою запись со статусом «занят», пока слот не начался.</p>
    </div>
  </div>
  @include('partials.errors')
  @if ($bookings->isEmpty())
    <p class="notice">Записей пока нет</p>
  @else
    <div class="table-wrap">
      <table class="data-table">
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
            <tr>
              <td>{{ $booking->slot->dateLabel() }}</td>
              <td>{{ $booking->slot->timeRange() }}</td>
              <td>{{ $booking->slot->teacherProfile->user->name }}</td>
              <td class="place">@include('partials.slot-place', ['slot' => $booking->slot])</td>
              <td class="soft">
                @if ($booking->slot->comment)
                  <div>{{ $booking->slot->comment }}</div>
                @endif
                @if ($booking->teacher_comment)
                  <div>{{ $booking->teacher_comment }}</div>
                @endif
                @if (! $booking->slot->comment && ! $booking->teacher_comment)
                  —
                @endif
              </td>
              <td><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></td>
              <td>
                @can('cancel', $booking)
                  <a class="btn btn-small btn-ghost" href="{{ route('bookings.cancel', $booking) }}">Отменить</a>
                @endcan
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
