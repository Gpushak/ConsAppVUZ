@php
  $inline = collect(isset($inlineErrors) ? $inlineErrors : []);
  $messageIds = $messageIds ?? [];
  $banner = [];
  $auto = [];
  foreach ($errors->getMessages() as $key => $messages) {
      if ($inline->contains($key)) {
          continue;
      }
      foreach ($messages as $error) {
          $id = $messageIds[$error] ?? null;
          if ($id === null && ! isset($auto[$key])) {
              $id = $key.'-error';
              $auto[$key] = true;
          }
          $banner[] = ['id' => $id, 'text' => $error];
      }
  }
@endphp
@if ($banner !== [])
  <div class="notice error">
    @foreach ($banner as $item)
      <div @if ($item['id']) id="{{ $item['id'] }}" @endif>{{ $item['text'] }}</div>
    @endforeach
  </div>
@endif
@if (session('status'))
  <p class="notice">{{ session('status') }}</p>
@endif
