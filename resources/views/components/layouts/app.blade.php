@php
    $setupTabUrl = route('records.setup.tab', ['tab' => '__TAB__']);
    $setupInitialTab = session('setup_tab', 'classifications');
    $setupInitiallyOpen = session()->has('setup_tab');
@endphp

@extends('layouts.app')

@section('title', ($title ?? 'Records') . ' — ORDO Records Management')

@section('content')
    <div class="p-6 space-y-6">
        {{ $slot }}
    </div>

    {{-- One copy of the setup modal for every page --}}
    @include('records.partials.setup-modal')
@endsection
