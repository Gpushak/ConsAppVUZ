@if ($mine || $slot->canBeBooked())
  @php
    $bookName = $mine ? 'Вы записаны' : 'Записаться';
    $bookLabel = $bookName.': '.$slot->teacherProfile->user->name.', '.$slot->dateLabel().', '.$slot->timeRange();
  @endphp
  <div class="actions slot-primary">
    @if ($mine)
      <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}" aria-label="{{ $bookLabel }}">Вы записаны</a>
    @else
      <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}" aria-label="{{ $bookLabel }}">Записаться</a>
    @endif
  </div>
@endif
