@php
  $activeOnly = $activeOnly ?? false;
  $withStatus = $withStatus ?? false;
  $withActions = $withActions ?? false;
  $open = $open ?? false;
  $inline = $inline ?? false;
  $groups = $slot->groupedBookings($activeOnly);
  $count = $activeOnly ? $slot->activeBookingsCount() : $slot->bookings->count();
  $label = $count === 0 ? 'нет записей' : \App\Support\Ru::students($count);
@endphp
<td class="people">
  @if ($inline)
    @if ($count === 0)
      <span class="muted">нет записей</span>
    @else
      <details class="people" @if ($open) open @endif>
        <summary>{{ $label }}</summary>
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
  @else
    <a @class(['signup-link', 'muted' => $count === 0]) href="{{ route('slots.roster', $slot) }}">{{ $label }}</a>
  @endif
</td>
