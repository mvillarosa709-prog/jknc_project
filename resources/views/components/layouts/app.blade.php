@extends('layouts.app')

@section('title', $title ?? 'Records')

@section('content')
<div class="p-6">
    {{ $slot }}
</div>
@endsection
