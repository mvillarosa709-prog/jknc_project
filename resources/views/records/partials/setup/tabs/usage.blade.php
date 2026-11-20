{{--
    Records Management Setup → Usage & Storage.

    Both meters are summed from the company's own records and uploads rather than
    read from a stored total, so they cannot drift from what the dashboard
    reports. Archived records stay in the sum: an archived record is still an
    official record and still consumes its Record Unit.

    The plan limits below are editable. They are billing inputs rather than a
    display preference, so they are treated as reference data and go through the
    same manageScheme ability as the rest of setup rather than being open to
    anyone who can see the figures.
--}}
@php
    // A null allowance means the company has no limit on file, which is not the
    // same as zero being used. The meter is omitted rather than shown as
    // "0 of 0", which would read as a company that has exhausted its allowance.
    $recordLimit = $recordUnitLimit === null ? null : (int) $recordUnitLimit;
    $recordPercent = ($recordLimit === null || $recordLimit <= 0)
        ? null
        : min(100, round($recordUnitsUsed / $recordLimit * 100, 1));

    // Storage is entered in gigabytes because that is the unit a plan is sold in,
    // and converted to the raw bytes the column holds.
    $storageLimitGb = $storageLimitBytes === null ? 0 : round($storageLimitBytes / 1073741824, 1);
@endphp

<div class="flex flex-col gap-3" x-data="{ editing: false }">
    <div class="grid gap-3 max-[860px]:grid-cols-1 sm:grid-cols-2">

        {{-- ============ RECORD CAPACITY ============ --}}
        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Record Capacity</h4>
                <span class="text-[10px] text-ordo-muted">Official records</span>
            </div>

            <div class="mt-3 flex items-baseline justify-between gap-3">
                <span class="text-[11px] text-ordo-muted">Used</span>
                <strong class="text-[12px] text-ordo-ink">
                    {{ number_format($recordUnitsUsed) }}
                    @if ($recordLimit !== null)
                        / {{ number_format($recordLimit) }}
                    @endif
                </strong>
            </div>

            @if ($recordPercent !== null)
                <div class="mt-2 h-[7px] w-full overflow-hidden rounded-full bg-[#e5e7eb]" role="presentation">
                    <div @class([
                            'h-full rounded-full',
                            'bg-ordo-blue' => $recordPercent < 90,
                            'bg-ordo-warn' => $recordPercent >= 90 && $recordPercent < 100,
                            'bg-ordo-danger' => $recordPercent >= 100,
                         ])
                         style="width: {{ $recordPercent }}%"></div>
                </div>
            @endif

            <p class="mb-0 mt-2.5 text-[10px] leading-[1.55] text-ordo-muted">
                @if ($recordLimit === null)
                    No Record Unit allowance has been set for this company.
                @endif
                Archived records remain official records and continue to count. Unclassified uploads
                do not consume a Record Unit until they are filed.
            </p>
        </div>

        {{-- ============ STORAGE ============ --}}
        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Storage</h4>
                <span class="text-[10px] text-ordo-muted">All retained file bytes</span>
            </div>

            <div class="mt-3 flex items-baseline justify-between gap-3">
                <span class="text-[11px] text-ordo-muted">Used</span>
                <strong class="text-[12px] text-ordo-ink">
                    @fileSize($storageBytesUsed)
                    @if ($storageLimitBytes !== null)
                        / @fileSize($storageLimitBytes)
                    @endif
                </strong>
            </div>

            @if ($storageLimitBytes)
                <div class="mt-2 h-[7px] w-full overflow-hidden rounded-full bg-[#e5e7eb]" role="presentation">
                    <div class="h-full rounded-full bg-ordo-blue"
                         style="width: {{ min(100, round($storageBytesUsed / $storageLimitBytes * 100, 1)) }}%"></div>
                </div>
            @endif

            <p class="mb-0 mt-2.5 text-[10px] leading-[1.55] text-ordo-muted">
                Master files, supporting attachments, prior versions and unclassified uploads all
                consume storage. Links never duplicate storage.
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-ordo-line bg-white p-4">
        <h4 class="m-0 text-[12px] font-bold text-ordo-ink">What This Counts</h4>

        <div class="mt-2.5 grid gap-2 max-[720px]:grid-cols-1 sm:grid-cols-2">
            <div class="rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2.5">
                <p class="m-0 text-[11px] font-semibold text-ordo-ink">{{ number_format($recordCount) }} filed record(s)</p>
                <p class="mb-0 mt-0.5 text-[10px] leading-[1.5] text-ordo-muted">Each consumed 1 Record Unit at filing.</p>
            </div>

            <div class="rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2.5">
                <p class="m-0 text-[11px] font-semibold text-ordo-ink">{{ number_format($intakeCount) }} unclassified upload(s)</p>
                <p class="mb-0 mt-0.5 text-[10px] leading-[1.5] text-ordo-muted">0 Record Units. Storage bytes only, until filed.</p>
            </div>
        </div>

        <p class="mb-0 mt-3 text-[10px] leading-[1.6] text-ordo-muted">
            <b class="text-ordo-ink">Metering rule:</b> one official logical record = one Record Unit.
            Supporting files and versions add storage only. Reusing the same record across ORDO
            modules adds links only — no additional Record Unit and no duplicated file.
        </p>
    </div>

    {{-- ============ PLAN LIMITS ============
         The ceilings the meters above are measured against. Zero means unlimited,
         which is stored as null so the rest of the application reads one thing. --}}
    @if ($canManage)
        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Plan Limits</h4>
                    <p class="mb-0 mt-0.5 text-[10px] text-ordo-muted">The ceilings the meters are measured against</p>
                </div>

                <x-ordo.button type="button" variant="soft"
                               x-on:click="editing = ! editing"
                               x-text="editing ? 'Cancel' : 'Edit Limits'">Edit Limits</x-ordo.button>
            </div>

            <form method="POST" action="{{ route('records.plan-limits.update') }}"
                  class="mt-3 grid gap-3 max-[720px]:grid-cols-1 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                  x-show="editing" x-cloak>
                @csrf
                @method('PUT')

                <x-ordo.field label="Record Capacity" help="Maximum Record Units. Use 0 for unlimited.">
                    <input type="number" name="record_unit_allocation" min="0" step="1" required
                           value="{{ old('record_unit_allocation', $recordLimit ?? 0) }}"
                           class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                </x-ordo.field>

                <x-ordo.field label="Storage Capacity (GB)" help="Maximum retained bytes. Use 0 for unlimited.">
                    <input type="number" name="storage_limit_gb" min="0" max="1000000" step="0.1" required
                           value="{{ old('storage_limit_gb', $storageLimitGb) }}"
                           class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                </x-ordo.field>

                <x-ordo.button type="submit" variant="primary">Save Limits</x-ordo.button>
            </form>
        </div>
    @endif
</div>

