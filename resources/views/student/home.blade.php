@extends('layouts.app')

@section('title', 'Слоты избранных')

@section('content')
  <div class="page-head">
    <h1>Слоты избранных</h1>
    @if ($slotsByTeacher->flatten()->contains(fn ($slot) => $slot->isFinished()))
      <button class="btn btn-ghost" type="button" data-finished-toggle aria-pressed="false">Показать завершённые</button>
    @endif
  </div>
  @include('partials.errors')
  @php
    $visible = $favorites->filter(fn ($teacher) => ($slotsByTeacher[$teacher->id] ?? collect())->isNotEmpty());
  @endphp
  @if ($favorites->isEmpty())
    <p class="notice">Избранных преподавателей нет</p>
  @elseif ($visible->isEmpty())
    <p class="notice">Свободных слотов нет</p>
  @else
    @foreach ($visible as $teacher)
      <section class="section">
        <h2>{{ $teacher->user->name }}</h2>
        <p class="meta">{{ $teacher->position }}, {{ $teacher->department }}</p>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Время</th>
                <th>Место</th>
                <th>Записались</th>
                <th>Комментарий</th>
                <th>Статус</th>
                <th data-skip>Действие</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($slotsByTeacher[$teacher->id] as $slot)
                @php
                  $mine = $slot->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
                @endphp
                <tr @if ($slot->isFinished()) data-finished hidden @endif>
                  <td>{{ $slot->dateLabel() }}</td>
                  <td>{{ $slot->timeRange() }}</td>
                  <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
                  @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true])
                  <td class="soft">{{ $slot->comment ?: '—' }}</td>
                  <td><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
                  <td>
                    @if ($mine)
                      <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
                    @elseif ($slot->canBeBooked())
                      <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}">Записаться</a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endforeach
  @endif
@endsection
