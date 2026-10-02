@extends('layouts.app')

@section('title', 'Travel Credits')

@section('content')
@php
    $availableUnits = (float) ($summary['available_units'] ?? 0);
    $creditValue = (float) ($summary['credit_value'] ?? 0);
    $availableValue = $availableUnits * $creditValue;
    $entries = collect($summary['entries'] ?? []);
@endphp

<div class="w-full px-4 sm:px-6 lg:px-8 mt-4 pb-8">
    <div class="overflow-hidden rounded-md border border-gray-100 bg-white">
        @include('company.partials.company-header', ['company' => $company])

        <section class="min-h-[720px] bg-gray-50 p-4 sm:p-6">
            <div class="mb-5">
                <h2 class="text-xl font-semibold text-gray-900">Travel Credits</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Manage the client travel-credit ledger used by Official Business Trip Tickets.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Available Credits
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        {{ number_format($availableUnits, 2) }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Credit Value
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        ₱{{ number_format($creditValue, 2) }}
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Available Value
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">
                        ₱{{ number_format($availableValue, 2) }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="font-semibold text-gray-900">
                            Add / Adjust Credits
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Positive units add credits. Negative units reduce the balance.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('activities.official-business.travel-credits.adjust', $company->id) }}"
                        class="space-y-4 p-5"
                    >
                        @csrf

                        <input type="hidden" name="entry_type" value="Adjustment">

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Credit Adjustment
                            </label>

                            <input
                                name="units_delta"
                                type="number"
                                step="0.01"
                                value="{{ old('units_delta') }}"
                                required
                                placeholder="Example: 5 or -2"
                                class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Credit Value
                            </label>

                            <input
                                name="credit_value"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value="{{ old('credit_value', number_format($creditValue, 2, '.', '')) }}"
                                class="h-10 w-full rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Reason
                            </label>

                            <textarea
                                name="reason"
                                rows="3"
                                required
                                placeholder="State why this adjustment is being made."
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >{{ old('reason') }}</textarea>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            Record Adjustment
                        </button>
                    </form>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="font-semibold text-gray-900">
                            Credit History
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Additions, usage, reversals and manual adjustments.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-right">Units</th>
                                    <th class="px-4 py-3 text-right">Unit Value</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-left">Trip Ticket</th>
                                    <th class="px-4 py-3 text-left">Reason</th>
                                    <th class="px-4 py-3 text-left">Recorded By</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @forelse ($entries as $entry)
                                    @php
                                        $units = (float) ($entry['units_delta'] ?? 0);
                                        $entryUnitValue = (float) ($entry['unit_value'] ?? 0);
                                        $createdAt = ! empty($entry['created_at'])
                                            ? \Illuminate\Support\Carbon::parse($entry['created_at'])
                                            : null;
                                    @endphp

                                    <tr class="text-gray-700">
                                        <td class="whitespace-nowrap px-4 py-3">
                                            {{ $createdAt ? $createdAt->format('M d, Y h:i A') : '-' }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                                            {{ $entry['entry_type'] ?? '-' }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold {{ $units < 0 ? 'text-red-600' : 'text-green-700' }}">
                                            {{ $units > 0 ? '+' : '' }}{{ number_format($units, 2) }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            ₱{{ number_format($entryUnitValue, 2) }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            {{ number_format((float) ($entry['balance_after'] ?? 0), 2) }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3">
                                            {{ ! empty($entry['trip_ticket_id']) ? '#'.$entry['trip_ticket_id'] : '-' }}
                                        </td>

                                        <td class="min-w-[240px] px-4 py-3">
                                            {{ $entry['reason'] ?? '-' }}
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3">
                                            {{ $entry['recorded_by'] ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">
                                            No Travel Credit transactions have been recorded for this company.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection