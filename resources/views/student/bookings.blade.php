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
      <table>
        <thead>
          <tr>
            <th>Дата</th>
            <th>Время</th>
            <th>Преподаватель</th>
            <th>Место</th>
            <th>Комментарий</th>
            <th>Статус</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($bookings as $booking)
            <tr>
              <td>{{ $booking->slot->dateLabel() }}</td>
              <td>{{ $booking->slot->timeRange() }}</td>
              <td>{{ $booking->slot->teacherProfile->user->name }}</td>
              <td>{{ $booking->slot->place() }}</td>
              <td class="soft">{{ $booking->teacher_comment ?: '—' }}</td>
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
