@if ($errors->any())
  <div class="notice error">
    @foreach ($errors->all() as $error)
      <div>{{ $error }}</div>
    @endforeach
  </div>
@endif
@if (session('status'))
  <p class="notice">{{ session('status') }}</p>
@endif
