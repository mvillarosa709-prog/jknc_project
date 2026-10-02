@extends('layouts.app')
@section('title', 'Trip Tickets')

@section('content')
<div class="p-6 lg:p-8">
    <h1 class="text-2xl font-semibold text-gray-900">Trip Tickets</h1>
    <p class="mt-1 text-sm text-gray-500">Completed trips for your company</p>

    <div class="mt-6 space-y-4">
        @forelse ($tickets as $ticket)
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ $ticket['reference_no'] }}</h2>
                        <p class="text-sm text-gray-600">{{ $ticket['client_name_snapshot'] }} · {{ $ticket['trip']['date_from'] ?? 'Date unavailable' }}</p>
                    </div>
                    <a class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                       href="{{ route('client.official-business.trip-tickets.pdf', $ticket['id']) }}">Download PDF</a>
                </div>

                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Destinations and activities</h3>
                        <ul class="mt-2 space-y-2 text-sm text-gray-600">
                            @forelse ($ticket['stops'] as $stop)
                                <li>{{ $stop['destination'] ?: 'Destination unavailable' }} — {{ $stop['activity'] ?: 'Activity unavailable' }}</li>
                            @empty
                                <li>No stops recorded.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Personnel</h3>
                        <ul class="mt-2 space-y-2 text-sm text-gray-600">
                            @forelse ($ticket['personnel'] as $member)
                                <li>{{ $member['employee_name_snapshot'] }}</li>
                            @empty
                                <li>No personnel recorded.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                @if (count($ticket['evidence']))
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h3 class="text-sm font-semibold text-gray-800">Evidence</h3>
                        <ul class="mt-2 space-y-1 text-sm text-gray-600">
                            @foreach ($ticket['evidence'] as $evidence)
                                <li>{{ $evidence['filename'] ?: 'Attachment' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600">No completed Trip Tickets are available for your company.</p>
        @endforelse
    </div>
</div>
@endsection
