{{-- System: retained metadata for wiring, metering, audit and future records controls.
     Fields the schema does not carry today are shown as Not configured rather than invented. --}}
<div x-show="activeTab === 'system'" x-cloak>
    <div class="mb-3 rounded-lg border border-dashed border-[#b8c7ef] bg-[#f8faff] px-3 py-2.5 text-[9px] leading-[1.55] text-[#475467]">
        <b>Future-ready system metadata.</b>
        Much of this is intentionally hidden from the classification form. It is retained for cross-module
        wiring, billing, storage metering, APIs, audit and security.
    </div>

    <div class="grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
        <div class="system-card">
            <span>Tenant / Company</span>
            <strong x-text="current ? current.tenantId + ' · ' + current.companyId : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Origin</span>
            <strong>Uploaded · Records Management</strong>
        </div>
        <div class="system-card">
            <span>Origin Module / Object</span>
            <strong x-text="current ? (current.intakeItemId ?? '—') : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Record Usage</span>
            <strong x-text="current ? current.recordUnits + ' unit · Counted' : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Storage</span>
            <strong x-text="current ? humanSize(current.storageBytes) + ' · ' + current.files.length + ' file(s)' : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Version</span>
            <strong x-text="current ? 'Version ' + latestVersion() : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Security</span>
            <strong x-text="current && current.isConfidential ? 'Confidential' : 'Internal'"></strong>
        </div>
        <div class="system-card">
            <span>Custody</span>
            <strong>Not configured</strong>
        </div>
        <div class="system-card">
            <span>Parent / Child</span>
            <strong>No parent &middot; 0 child record(s)</strong>
        </div>
        <div class="system-card">
            <span>Retention / Hold</span>
            <strong x-text="current ? (current.retentionPeriod ?? 'Not configured') + ' · ' + (current.isOnLegalHold ? 'Legal Hold' : 'No Hold') : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Integrity</span>
            <strong class="break-all" x-text="master && master.checksum ? master.checksum.slice(0, 18) + '…' : 'Hash pending'"></strong>
        </div>
        <div class="system-card">
            <span>Relationships</span>
            <strong x-text="current ? current.linkCount + ' active link(s)' : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Filed</span>
            <strong x-text="current ? (current.filedAt ?? '—') : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Filed By</span>
            <strong x-text="current ? (current.filedBy ?? '—') : '—'"></strong>
        </div>

        {{-- The two wiring cards the reference shows that this schema can carry.

             Storage Key is real: the disk and the key are on the record. The
             audit action is this codebase's own — record.filed, not the
             reference's record.classified — because the history has to name the
             event that actually happened. --}}
        <div class="system-card">
            <span>Storage Key</span>
            <strong class="break-all"
                    x-text="current ? current.storageDisk + ' · ' + (current.storageKey || '—') : '—'"></strong>
        </div>
        <div class="system-card">
            <span>Integration</span>
            <strong>v1 &middot; record.filed</strong>
        </div>
    </div>

    <table class="mt-3 w-full border-separate border-spacing-0 overflow-hidden rounded-[9px] border border-ordo-line">
        <tr><td>Created</td><td x-text="current ? (current.createdAt ?? '—') : '—'"></td></tr>
        <tr><td>Last Updated</td><td x-text="current ? (current.updatedAt ?? '—') : '—'"></td></tr>
        <tr><td>Full SHA-256</td><td class="break-all" x-text="master ? (master.checksum ?? 'Not available') : '—'"></td></tr>
    </table>
</div>