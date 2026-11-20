{{--
    Records Management Setup → Integration.

    Three kinds of truth on one screen, deliberately kept apart:

    * Measured — the link figures, read from the polymorphic links table.
    * Planned — the module mapping. It names codes; where this company's scheme
      does not carry them the row says so, because that is the only thing on this
      tab an integrator can act on.
    * Not built — the wiring table marks Recordable and Brevo, which the Core
      Pattern card names and this codebase does not have.

    The two actions are real. "Simulate Generated Document" files an actual
    record through the ordinary intake and filing path, which costs a Record Unit
    and says so on the button. "Test Global Record Picker" is the same link
    endpoint the record drawer uses, started from the other direction.
--}}
<div class="flex flex-col gap-3">

    {{-- ============ STACK AND PATTERN ============ --}}
    <div class="grid gap-3 max-[980px]:grid-cols-1 lg:grid-cols-3">
        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <span class="text-[10px] uppercase tracking-[0.04em] text-ordo-faint">Current ORDO Stack</span>
            <strong class="mt-1 block text-[12px] text-ordo-ink">Use the existing application architecture</strong>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span class="rounded-full bg-[#f3f4f6] px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.04em] text-[#4b5563]">Laravel</span>
                <span class="rounded-full bg-[#f3f4f6] px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.04em] text-[#4b5563]">PHP</span>
                <span class="rounded-full bg-[#f3f4f6] px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.04em] text-[#4b5563]">MySQL</span>
                {{-- Named by the reference stack, not installed here. The wiring
                         table below says so rather than leaving this badge to
                         imply otherwise. --}}
                <span class="rounded-full bg-[#f3f4f6] px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.04em] text-[#4b5563]">Brevo</span>
            </div>
        </div>

        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <span class="text-[10px] uppercase tracking-[0.04em] text-ordo-faint">Integration Mode</span>
            <strong class="mt-1 block text-[12px] text-ordo-ink">Additive shared module</strong>
            <p class="mb-0 mt-1.5 text-[10px] leading-[1.55] text-ordo-muted">
                Do not rebuild current ORDO modules. Connect them progressively to the central
                Records engine.
            </p>
        </div>

        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <span class="text-[10px] uppercase tracking-[0.04em] text-ordo-faint">Core Pattern</span>
            <strong class="mt-1 block text-[12px] text-ordo-ink">RecordService + Recordable + polymorphic record_links</strong>
            <p class="mb-0 mt-1.5 text-[10px] leading-[1.55] text-ordo-muted">
                The same Record can be reused anywhere without duplicating its master file.
            </p>
        </div>
    </div>

    {{-- ============ PLANNED MAPPING ============ --}}
    <div class="rounded-xl border border-ordo-line bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Generated Document Mapping</h4>
            <span class="text-[10px] text-ordo-muted">Module &rarr; Records</span>
        </div>

        <p class="mb-3 mt-1.5 text-[10px] leading-[1.55] text-ordo-muted">
            When a module-generated document reaches the configured final state, the module can
            register it directly as an official Record. Drafts remain working objects.
        </p>

        <div class="overflow-x-auto rounded-lg border border-ordo-line">
            <table class="w-full border-collapse text-[11px]">
                <thead>
                    <tr class="border-b border-ordo-line bg-[#fbfcfe] text-left">
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Module</th>
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Generated Document</th>
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Trigger</th>
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Classification</th>
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Index</th>
                        <th class="px-3 py-2 font-semibold text-ordo-muted">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mappings as $position => $mapping)
                        <tr class="border-b border-ordo-line last:border-b-0">
                            <td class="px-3 py-2 text-ordo-ink">{{ $mapping['module'] }}</td>
                            <td class="px-3 py-2 text-ordo-ink">{{ $mapping['document'] }}</td>
                            <td class="px-3 py-2 text-ordo-muted">{{ $mapping['trigger'] }}</td>
                            {{-- The codes are the pairing's own; the names come from
                                 this company's scheme when it carries them, so a row
                                 that resolves reads differently from one that does
                                 not even before the status column is reached. --}}
                            <td class="px-3 py-2">
                                <span class="font-mono text-ordo-muted">{{ $mapping['classification'] }}</span>
                                @if ($mapping['classificationName'])
                                    <small class="mt-0.5 block text-[9px] text-ordo-faint">{{ $mapping['classificationName'] }}</small>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <span class="font-mono text-ordo-muted">{{ $mapping['index'] }}</span>
                                @if ($mapping['indexName'])
                                    <small class="mt-0.5 block text-[9px] text-ordo-faint">{{ $mapping['indexName'] }}</small>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <x-ordo.pill :tone="$mapping['statusTone']" :title="$mapping['statusHint']">{{ $mapping['status'] }}</x-ordo.pill>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="m-0 text-[10px] leading-[1.55] text-ordo-muted">
                Every mapping above is <b>planned</b>. None is connected to a live module yet &mdash;
                Corporate Governance is delivered after this foundation is accepted.
            </p>

            <div class="flex shrink-0 items-center gap-2">
                @if (collect($mappings)->contains('inScheme', true))
                    {{-- The select picks which pairing to file. Rows this company's
                         scheme cannot resolve are disabled rather than hidden, so
                         the reader can see what is planned and what is blocked in
                         one glance. --}}
                    <form method="POST" action="{{ route('records.integration.simulate') }}" class="flex items-center gap-2">
                        @csrf
                        <select name="mapping" required
                                class="max-w-[240px] rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[11px] outline-none focus:border-ordo-blue">
                            @foreach ($mappings as $position => $mapping)
                                <option value="{{ $position }}" @disabled(! $mapping['inScheme'])>
                                    {{ $mapping['module'] }} &mdash; {{ $mapping['document'] }}
                                </option>
                            @endforeach
                        </select>
                        <x-ordo.button type="submit" variant="primary" class="whitespace-nowrap">
                            Simulate Generated Document
                        </x-ordo.button>
                    </form>
                @else
                    {{-- Nothing in the table can be simulated. Offering the button
                         anyway would only produce a validation failure, so the
                         reason is given instead. --}}
                    <span class="rounded-lg border border-[#f5c78a] bg-[#fff6e5] px-3 py-2 text-[10px] leading-[1.5] text-ordo-warn">
                        No mapping can be simulated yet &mdash; this scheme carries none of
                        the Classification and Index codes the table names.
                    </span>
                @endif

                <x-ordo.button type="button" variant="default" class="whitespace-nowrap"
                               x-on:click="$dispatch('ordo:integration-picker-open')">
                    Test Global Record Picker
                </x-ordo.button>
            </div>
        </div>

        <p class="mb-0 mt-2 text-[10px] leading-[1.55] text-ordo-muted">
            <b>Simulate Generated Document</b> creates a real intake item and files it through the
            ordinary path, then reports the Record ID and Document Reference it was given. It costs
            <b>1 Record Unit</b>, like any filing. Rows whose Classification or Index is not yet in
            this company's scheme cannot be simulated until it is.
        </p>
    </div>

    {{-- ============ UNREGISTERED UPLOADS AND WIRING ============ --}}
    <div class="grid gap-3 max-[980px]:grid-cols-1 lg:grid-cols-2">
        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <div class="flex items-start justify-between gap-2">
                <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Unregistered Uploads</h4>
                <span class="shrink-0 text-[10px] text-ordo-muted">Backward compatible</span>
            </div>
            <p class="mb-0 mt-1 text-[10px] leading-[1.55] text-ordo-muted">
                Files already uploaded to ORDO keep working. Register them into Records when they
                are worth keeping.
            </p>

            <div class="mt-3 flex flex-col gap-2">
                @forelse ($unregistered as $upload)
                    <div class="flex items-center gap-2.5 rounded-lg border border-ordo-line px-3 py-2">
                        <div class="min-w-0 flex-1">
                            <strong class="block truncate font-mono text-[11px] text-ordo-ink">{{ $upload['name'] }}</strong>
                            <small class="mt-0.5 block text-[9px] text-ordo-faint">
                                {{ $upload['title'] }} &middot; @fileSize($upload['sizeBytes'])
                                @if ($upload['fileCount'] > 1)
                                    &middot; {{ $upload['fileCount'] }} files
                                @endif
                            </small>
                        </div>
                        <x-ordo.pill tone="neutral">Unregistered</x-ordo.pill>
                        <a href="{{ route('records.unclassified') }}?open={{ $upload['id'] }}"
                           class="shrink-0 rounded-lg border border-ordo-blue-border bg-[#f4f7ff] px-3 py-1.5 text-[11px] text-ordo-blue transition-colors hover:bg-[#e9f0ff]">
                            Register
                        </a>
                    </div>
                @empty
                    <p class="mb-0 text-[11px] text-ordo-muted">
                        Nothing is waiting to be registered. Every uploaded file has been filed or discarded.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-ordo-line bg-white p-4">
            <div class="flex items-start justify-between gap-2">
                <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Shared Service Wiring</h4>
                <span class="shrink-0 text-[10px] text-ordo-muted">Developer reference</span>
            </div>

            {{-- The Core Pattern card names Recordable and Brevo, and neither is
                 built here. Saying so on the row is the difference between a
                 contract another module can code against and a table that
                 implies two services exist. --}}
            <dl class="mt-3 divide-y divide-ordo-line overflow-hidden rounded-lg border border-ordo-line">
                @foreach ($wiring as [$service, $target, $built])
                    <div class="grid grid-cols-[minmax(0,150px)_1fr] items-baseline gap-3 px-3 py-2">
                        <dt class="flex flex-wrap items-center gap-1.5 text-[11px] text-ordo-ink">
                            {{ $service }}
                            @unless ($built)
                                <x-ordo.pill tone="red" title="Named by the Core Pattern above. Not implemented in this codebase.">planned</x-ordo.pill>
                            @endunless
                        </dt>
                        <dd class="m-0 break-all font-mono text-[10px] text-ordo-muted">{{ $target }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-3 rounded-lg border border-l-[3px] border-ordo-blue bg-[#f8faff] px-3 py-2.5 text-[10px] leading-[1.6] text-[#475467]">
                <b>Recommended event boundary.</b>
                Modules subscribe to what the engine already records:
                @foreach (['record.filed', 'record.linked', 'record.unlinked', 'record.archived', 'record.file_added', 'record.version_created'] as $event)
                    <span class="font-mono">{{ $event }}</span>@if (! $loop->last),@endif
                @endforeach.
                Billing and notifications listen to the trail rather than being hard-coded into modules.
            </div>
        </div>
    </div>

    {{-- ============ LIVE LINK FIGURES ============ --}}
    <div class="rounded-xl border border-ordo-line bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Connected Across ORDO</h4>
                <p class="mb-0 mt-0.5 text-[10px] text-ordo-muted">Master record reuse, as it stands today</p>
            </div>
        </div>

        <div class="mt-3 grid gap-2 max-[720px]:grid-cols-1 sm:grid-cols-2">
            <div class="rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2.5">
                <strong class="block text-[16px] text-ordo-ink">{{ number_format($linkCount) }}</strong>
                <span class="text-[10px] text-ordo-muted">Total links</span>
            </div>

            <div class="rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2.5">
                <strong class="block text-[16px] text-ordo-ink">{{ number_format($linkedRecords) }}</strong>
                <span class="text-[10px] text-ordo-muted">Records reused elsewhere</span>
            </div>
        </div>

        @if ($byModule->isNotEmpty())
            <div class="mt-3 flex flex-col gap-2">
                @foreach ($byModule as $module => $count)
                    <div class="flex items-center gap-3">
                        <span class="w-[190px] shrink-0 truncate text-[11px] text-ordo-ink">{{ $module }}</span>
                        <span class="h-[7px] flex-1 overflow-hidden rounded-full bg-[#e5e7eb]" role="presentation">
                            <span class="block h-full rounded-full bg-ordo-blue"
                                  style="width: {{ round($count / $byModule->max() * 100) }}%"></span>
                        </span>
                        <span class="w-[42px] shrink-0 text-right text-[11px] text-ordo-muted">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mb-0 mt-3 text-[11px] text-ordo-muted">No cross-module links yet.</p>
        @endif
    </div>

    {{-- ============ RECENT INTEGRATION EVENTS ============ --}}
    <div class="rounded-xl border border-ordo-line bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="m-0 text-[12px] font-bold text-ordo-ink">Recent Usage / Integration Events</h4>
            <span class="text-[10px] text-ordo-muted">{{ count($usageEvents) }} shown</span>
        </div>

        @forelse ($usageEvents as $event)
            <div class="mt-2.5 flex flex-wrap items-center gap-3 border-b border-ordo-line pb-2.5 last:border-b-0 last:pb-0">
                <span class="shrink-0 text-[10px] text-ordo-muted">{{ $event['atLabel'] }}</span>
                <span class="rounded-full bg-ordo-blue-soft px-2 py-1 text-[9px] text-ordo-blue">{{ $event['action'] }}</span>
                {{-- Filing is the only action in this list that created a
                     billable Record; everything else is bookkeeping. --}}
                <span class="shrink-0 text-[10px] text-ordo-muted">{{ $event['recordDelta'] }} record</span>
                <span class="min-w-0 flex-1 truncate text-[11px] text-ordo-ink">
                    @if ($event['reference'])
                        <b class="font-mono">{{ $event['reference'] }}</b> &middot;
                    @endif
                    {{ $event['note'] }}
                </span>
            </div>
        @empty
            <p class="mb-0 mt-2 text-[11px] text-ordo-muted">
                Nothing has been filed or linked yet. This fills in as the engine is used.
            </p>
        @endforelse
    </div>

    {{-- The picker travels with this fragment rather than being fetched on the
         button press: the tab is already only fetched when it is opened, so a
         second round trip would buy nothing. Its own Alpine root, like every
         other modal, so Escape closes it and nothing else. --}}
    @include('records.partials.global-record-picker')
</div>