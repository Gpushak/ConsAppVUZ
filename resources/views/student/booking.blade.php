@extends('layouts.app')

@section('title', 'Запись')

@section('content')
  <div class="page-head">
    <div>
      <h1>Запись</h1>
      <p class="lead">{{ $booking->slot->teacherProfile->user->name }}, {{ $booking->slot->dateLabel() }}, {{ $booking->slot->timeRange() }}.</p>
    </div>
  </div>
  @include('partials.errors')
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Преподаватель</th>
          <th>Дата</th>
          <th>Время</th>
          <th>Место</th>
          <th>Комментарий</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $booking->slot->teacherProfile->user->name }}</td>
          <td>{{ $booking->slot->dateLabel() }}</td>
          <td>{{ $booking->slot->timeRange() }}</td>
          <td>{{ $booking->slot->place() }}</td>
          <td class="soft">{{ $booking->teacher_comment ?: ($booking->slot->comment ?: '—') }}</td>
          <td><span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
  @can('cancel', $booking)
    <p class="hint"><a href="{{ route('bookings.cancel', $booking) }}">Отменить запись</a></p>
  @endcan
  <p class="hint"><a href="{{ route('bookings.index') }}">Мои записи</a></p>
@endsection
