@extends('layouts.app')

@section('title', 'Мои слоты')

@section('content')
  <div class="page-head">
    <div>
      <h1>Мои слоты</h1>
      <p class="lead">Слоты и записавшиеся. Группы по алфавиту, внутри группы — фамилии.</p>
    </div>
    <a class="btn" href="{{ route('teacher.slots.create') }}">Новый слот</a>
  </div>
  @include('partials.errors')
  @php
    $openId = $slots->first(fn ($slot) => $slot->bookings->isNotEmpty())?->id;
  @endphp
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Дата</th>
          <th>Время</th>
          <th>Место</th>
          <th>Студенты</th>
          <th>Комментарий</th>
          <th>Статус</th>
          <th data-skip>Действие</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($slots as $slot)
          <tr>
            <td>{{ $slot->dateLabel() }}</td>
            <td>{{ $slot->timeRange() }}</td>
            <td class="place">@include('partials.slot-place', ['slot' => $slot])</td>
            @include('partials.signup-list', [
              'slot' => $slot,
              'withStatus' => true,
              'withActions' => true,
              'open' => $slot->id === $openId,
            ])
            <td class="soft">{{ $slot->comment ?: '—' }}</td>
            <td><span class="chip {{ $slot->chipClass() }}">{{ $slot->statusLabel() }}</span></td>
            <td class="fit">
              <div class="actions">
                @if ($slot->canBeConducted())
                  <a class="btn btn-small" href="{{ route('teacher.slots.conduct', $slot) }}">Отметить проведённой</a>
                @endif
                @if ($slot->canBeUnpublished())
                  <a class="btn btn-small btn-ghost" href="{{ route('teacher.slots.unpublish.confirm', $slot) }}">Снять с публикации</a>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr data-static>
            <td colspan="7" class="muted">Слотов пока нет.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
