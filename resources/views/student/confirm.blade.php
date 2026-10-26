@extends('layouts.app')

@section('title', 'Запись на слот')

@section('content')
  <div class="page-head">
    <div>
      <h1>Подтверждение записи</h1>
      <p class="lead">Слот групповой: записаться могут и другие студенты.</p>
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
          <th>Записались</th>
          <th>Комментарий</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $slot->teacherProfile->user->name }}, {{ $slot->teacherProfile->position }}</td>
          <td>{{ $slot->dateLabel() }}</td>
          <td>{{ $slot->timeRange() }}</td>
          <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
          @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true, 'open' => true])
          <td class="soft">{{ $slot->comment ?: '—' }}</td>
          <td><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
  @if ($mine)
    <p class="notice">Вы уже записаны на этот слот.</p>
    <p class="hint"><a href="{{ route('bookings.show', $mine) }}">Открыть запись</a></p>
  @elseif ($slot->isFree())
    <form method="POST" action="{{ route('bookings.store', $slot) }}">
      @csrf
      <p class="form-actions"><button class="btn" type="submit">Записаться</button></p>
    </form>
  @else
    <p class="notice">На этот слот уже нельзя записаться.</p>
  @endif
  <p class="hint"><a href="{{ route('home') }}">К слотам избранных</a></p>
@endsection
