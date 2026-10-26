@extends('layouts.app')

@section('title', 'Слоты избранных')

@section('content')
  <div class="page-head">
    <div>
      <h1>Слоты избранных</h1>
      <p class="lead">Предстоящие консультации избранных преподавателей. Слот открыт, пока на него можно записаться.</p>
    </div>
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
          <table>
            <thead>
              <tr>
                <th>Дата</th>
                <th>Время</th>
                <th>Место</th>
                <th>Записались</th>
                <th>Комментарий</th>
                <th>Статус</th>
                <th>Действие</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($slotsByTeacher[$teacher->id] as $slot)
                @php
                  $mine = $slot->bookings->first(fn ($booking) => $booking->student_profile_id === $student->id && $booking->isActive());
                @endphp
                <tr>
                  <td>{{ $slot->dateLabel() }}</td>
                  <td>{{ $slot->timeRange() }}</td>
                  <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
                  @include('partials.signup-list', ['slot' => $slot, 'activeOnly' => true])
                  <td class="soft">{{ $slot->comment ?: '—' }}</td>
                  <td><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
                  <td>
                    @if ($mine)
                      <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
                    @else
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
