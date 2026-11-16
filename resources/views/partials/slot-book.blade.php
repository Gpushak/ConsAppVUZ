@if ($mine || $slot->canBeBooked())
  <div class="actions slot-primary">
    @if ($mine)
      <a class="btn btn-small btn-ghost" href="{{ route('bookings.show', $mine) }}">Вы записаны</a>
    @else
      <a class="btn btn-small" href="{{ route('bookings.create', $slot) }}">Записаться</a>
    @endif
  </div>
@endif
