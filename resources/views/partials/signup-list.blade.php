@php
  $activeOnly = $activeOnly ?? false;
  $withStatus = $withStatus ?? false;
  $withActions = $withActions ?? false;
  $open = $open ?? false;
  $groups = $slot->groupedBookings($activeOnly);
  $count = $activeOnly ? $slot->activeBookingsCount() : $slot->bookings->count();
@endphp
<td class="people">
  @if ($count === 0)
    <span class="muted">нет записей</span>
  @else
    <details class="people" @if ($open) open @endif>
      <summary>{{ \App\Support\Ru::students($count) }}</summary>
      @foreach ($groups as $group => $groupBookings)
        <p class="group-name">{{ $group }}</p>
        <ul>
          @foreach ($groupBookings as $booking)
            <li @class(['me' => auth()->id() === $booking->studentProfile->user_id])>
              {{ $booking->studentProfile->user->name }}
              @if ($withStatus)
                <span class="chip {{ $booking->chipClass() }}">{{ $booking->label() }}</span>
              @endif
              @if ($withStatus && filled($booking->teacher_comment))
                <span class="muted">{{ $booking->teacher_comment }}</span>
              @endif
              @if ($withActions && $booking->isActive())
                <a class="btn btn-small btn-ghost" href="{{ route('teacher.bookings.cancel', $booking) }}">Отменить</a>
              @endif
            </li>
          @endforeach
        </ul>
      @endforeach
    </details>
  @endif
</td>
