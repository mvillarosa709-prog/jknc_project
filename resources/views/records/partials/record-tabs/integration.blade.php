{{-- Integration: what the implementation exposes for cross-module wiring. --}}
<div x-show="activeTab === 'integration'" x-cloak>
    <div class="rounded-lg border-l-[3px] border-ordo-blue bg-[#f8faff] px-3 py-2.5 text-[9px] leading-[1.6] text-[#475467]">
        <b>Integration-ready master record.</b>
        This tab shows the wiring the Laravel implementation exposes so this Record can be connected to
        existing and future ORDO modules without copying the file.
    </div>

    <div class="mt-3 grid grid-cols-3 gap-2.5 max-[860px]:grid-cols-1">
        <div class="integration-card">
            <span>Origin Object</span>
            <strong x-text="current ? (current.intakeItemId ? 'Intake Item · ' + current.intakeItemId : '—') : '—'"></strong>
        </div>
        <div class="integration-card">
            <span>Recordable</span>
            <strong>Not configured</strong>
        </div>
        {{-- v1 is the truth here: this wiring is the first shape of it, and it is
             the value the reference shows too. --}}
        <div class="integration-card">
            <span>Integration Version</span>
            <strong>v1</strong>
        </div>
    </div>

    <div class="mt-3 rounded-xl border border-ordo-line bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="m-0 text-[11px] font-bold">Laravel Wiring</h4>
            <span class="text-[9px] text-ordo-muted">Additive shared module</span>
        </div>
        <table class="w-full border-separate border-spacing-0 overflow-hidden rounded-[9px] border border-ordo-line">
            <tr><td>Record Service</td><td>App\Services\Records\RecordService</td></tr>
            <tr><td>Filing Service</td><td>App\Services\Records\RecordFilingService</td></tr>
            <tr><td>Relationship Store</td><td>record_links &middot; polymorphic</td></tr>
            <tr><td>Storage</td><td x-text="current ? current.storageDisk + ' → ' + current.storageKey : '—'"></td></tr>
            <tr><td>Activity Log</td><td>record_activity_logs &middot; append-only</td></tr>
            <tr><td>Background Work</td><td>Laravel Queue &middot; ProcessIntakeOcr</td></tr>

            {{-- The reference lists two more rows here. Neither is built, so they
                 are carried as declared intent rather than dropped — and marked, so
                 this panel never reads as though App\Models\Concerns\Recordable
                 and a Brevo notification channel exist in this codebase. --}}
            <tr>
                <td>Recordable Trait / Contract</td>
                <td class="text-ordo-faint">App\Models\Concerns\Recordable &middot; planned, not implemented</td>
            </tr>
            <tr>
                <td>Notifications</td>
                <td class="text-ordo-faint">Laravel Notifications → Brevo &middot; planned, not implemented</td>
            </tr>
        </table>
    </div>

    <div class="mt-3 rounded-xl border border-ordo-line bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="m-0 text-[11px] font-bold">Relationship Wiring</h4>
            <span class="text-[9px] text-ordo-muted" x-text="(current ? current.linkCount : 0) + ' active link(s)'"></span>
        </div>
        <template x-for="link in (current ? current.links : [])" x-bind:key="link.id">
            <div class="mb-1.5 flex items-center justify-between gap-3 rounded-lg border border-[#e6eaf0] bg-[#fafbfc] px-3 py-2">
                <div class="min-w-0">
                    <div class="text-[9px] uppercase tracking-[0.05em] text-[#98a2b3]" x-text="link.module"></div>
                    <div class="mt-0.5 text-[9px] text-[#98a2b3]" x-text="link.linkTypeLabel"></div>
                </div>
                <strong class="text-[10px]" x-text="link.linkedId"></strong>
            </div>
        </template>
        <template x-if="! current || current.links.length === 0">
            <x-ordo.empty message="No linked ORDO objects yet." />
        </template>
    </div>

    <div class="mt-3 rounded-xl border border-ordo-line bg-white p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="m-0 text-[11px] font-bold">Usage / Billing Events</h4>
            <span class="text-[9px] text-ordo-muted">Record + storage metering</span>
        </div>
        <div class="text-[9px] leading-[1.6] text-ordo-muted">
            Record classified → +1 Record Unit &middot; Supporting file/version → storage only &middot;
            Link → 0 Record Units and 0 duplicate storage.
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <x-ordo.pill tone="blue">record.classified</x-ordo.pill>
            <x-ordo.pill>
                <span x-text="current ? current.recordUnits + ' Record Unit' : '—'"></span>
            </x-ordo.pill>
            <x-ordo.pill>
                <span x-text="current ? humanSize(current.storageBytes) : '—'"></span>
            </x-ordo.pill>
        </div>
    </div>
</div>