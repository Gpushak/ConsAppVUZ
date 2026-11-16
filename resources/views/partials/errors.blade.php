@php
  $inline = collect(isset($inlineErrors) ? $inlineErrors : []);
  $banner = collect($errors->getMessages())
      ->reject(fn ($messages, $key) => $inline->contains($key))
      ->flatten();
@endphp
@if ($banner->isNotEmpty())
  <div class="notice error">
    @foreach ($banner as $error)
      <div>{{ $error }}</div>
    @endforeach
  </div>
@endif
@if (session('status'))
  <p class="notice">{{ session('status') }}</p>
@endif
