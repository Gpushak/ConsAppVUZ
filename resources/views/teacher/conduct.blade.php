@extends('layouts.app')

@section('title', 'Консультация проведена')

@section('content')
  <div class="page-head">
    <h1>Отметить проведённой</h1>
  </div>
  @include('partials.errors', ['inlineErrors' => ['comment']])
  <p class="notice">Активные записи получат статус «проведён».</p>
  <form class="panel" method="POST" action="{{ route('teacher.slots.conduct.store', $slot) }}">
    @csrf
    <div class="field">
      <label for="comment">Комментарий, необязательно</label>
      <textarea id="comment" name="comment" maxlength="500" @if ($errors->has('comment')) aria-invalid="true" aria-describedby="comment-error" @endif>{{ old('comment') }}</textarea>
      @error('comment')
        <p class="error" id="comment-error">{{ $message }}</p>
      @enderror
    </div>
    <p class="form-actions"><button class="btn" type="submit">Отметить проведённой</button></p>
  </form>
  <p class="hint"><a href="{{ route('teacher.slots.index') }}">К моим слотам</a></p>
@endsection
