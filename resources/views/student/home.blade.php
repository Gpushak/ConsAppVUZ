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
    $withSlots = $favorites->filter(fn ($teacher) => ($slotsByTeacher[$teacher->id] ?? collect())->isNotEmpty());
    $openTeachers = $favorites->filter(fn ($teacher) => ($slotsByTeacher[$teacher->id] ?? collect())->contains(fn ($slot) => ! $slot->isFinished()));
    $hasFinished = $slotsByTeacher->flatten()->contains(fn ($slot) => $slot->isFinished());
  @endphp
  @if ($favorites->isEmpty())
    <p class="notice">Избранных преподавателей нет. <a href="{{ route('teachers.index') }}">Откройте преподавателей</a> и отметьте, чьи слоты смотреть.</p>
  @elseif ($openTeachers->isEmpty())
    <p class="notice">Свободных слотов нет. @if ($hasFinished)Нажмите «Показать завершённые» или <a href="{{ route('teachers.index') }}">откройте преподавателей</a>.@else<a href="{{ route('teachers.index') }}">Откройте преподавателей</a>, чтобы выбрать, чьи слоты смотреть.@endif</p>
  @endif
  @if ($withSlots->isNotEmpty())
    <div @if ($openTeachers->isEmpty()) data-only-finished hidden @endif>
    @foreach ($withSlots as $teacher)
      <section class="section">
        <h2>{{ $teacher->user->name }}</h2>
        <p class="meta">{{ $teacher->position }}, {{ $teacher->department }}</p>
        <div class="table-wrap slot-cards">
          <table class="data-table slot-table">
            <thead>
              <tr>
                <th>Когда</th>
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
                <tr class="slot-row" @if ($slot->isFinished()) data-finished hidden @endif>
                  <td class="slot-when">
                    <span class="slot-date-text">{{ $slot->dateLabel() }}</span>
                    <span class="slot-time-text">{{ $slot->timeRange() }}</span>
                  </td>
                  <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
                  @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true])
                  <td @class(['soft', 'slot-comment', 'is-empty' => ! $slot->comment])>{{ $slot->comment ?: '—' }}</td>
                  <td class="slot-status"><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
                  <td class="slot-actions">
                    @if ($mine || $slot->canBeBooked())
                      <div class="actions slot-primary">
                        @if ($mine)
                          <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
                        @else
                          <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}">Записаться</a>
                        @endif
                      </div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endforeach
    </div>
  @endif
@endsection
