{{-- Files: the master document plus any supporting files and versions. --}}
<div x-show="activeTab === 'files'" x-cloak>
    <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-ordo-line bg-[#fafbfc] px-3 py-2.5">
        <div class="min-w-0">
            <div class="text-[10px] font-semibold text-[#4b5563]">Master Document</div>
            <div class="mt-0.5 text-[9px] leading-[1.45] text-[#98a2b3]">
                One official Record can hold a master file, supporting files and versions. Supporting files
                and versions add storage but do not add another Record Unit.
            </div>
        </div>
        <span class="shrink-0 rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
              x-text="current ? (current.documentReference ?? '—') : '—'"></span>
    </div>

    {{-- The reference's three file actions. Supporting files and versions attach
         bytes to a Record already on the books and consume no further Record Unit;
         uploading as a Record files something new and consumes one.

         The two forms post to a URL template the controller supplies, because the
         drawer is mounted once for the page while these act on whichever record is
         open — so the id is only known at runtime. --}}
    <div class="mb-2.5 flex flex-wrap items-center gap-1.5">
        <form method="POST" enctype="multipart/form-data" x-bind:action="recordUrl(supportingUrl)">
            @csrf
            <input type="file" name="file" class="sr-only" id="ordo-supporting-file"
                   accept="{{ collect(config('ordo.document_extensions'))->map(fn ($ext) => ".{$ext}")->join(',') }}"
                   x-on:change="$el.form.submit()"
                   x-bind:disabled="! current || archived" />
            <label for="ordo-supporting-file"
                   class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-[12px] transition-colors border-ordo-blue bg-ordo-blue text-white hover:bg-[#2559d6]"
                   x-bind:class="(! current || archived) && 'pointer-events-none opacity-50'">+ Supporting File</label>
        </form>

        <form method="POST" enctype="multipart/form-data" x-bind:action="recordUrl(versionUrl)">
            @csrf
            <input type="file" name="file" class="sr-only" id="ordo-version-file"
                   accept="{{ collect(config('ordo.document_extensions'))->map(fn ($ext) => ".{$ext}")->join(',') }}"
                   x-on:change="$el.form.submit()"
                   x-bind:disabled="! current || archived" />
            <label for="ordo-version-file"
                   class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-[12px] transition-colors border-ordo-line bg-ordo-panel text-[#374151] hover:bg-[#f9fafb]"
                   x-bind:class="(! current || archived) && 'pointer-events-none opacity-50'">+ New Version</label>
        </form>

        <x-ordo.button type="button" variant="default"
                       class="whitespace-nowrap"
                       x-bind:disabled="! current || archived"
                       x-on:click="$dispatch('ordo:upload-as-record-open')">+ Upload as Record</x-ordo.button>
    </div>

    <div class="mb-2.5 flex items-center justify-between gap-2 rounded-lg border border-ordo-line bg-white px-3 py-2.5">
        <div class="flex items-center gap-2.5">
            <div class="grid h-10 w-[34px] shrink-0 place-items-center rounded-md border border-ordo-line bg-[#f3f4f6] text-[10px] text-ordo-muted"
                 x-text="master ? glyph(master) : 'FILE'"></div>
            <div class="min-w-0">
                <strong class="block max-w-[260px] truncate text-[11px]" x-text="master ? master.name : 'Master file'"></strong>
                <small class="block text-[9px] text-ordo-faint"
                        x-text="master ? master.documentType + ' · ' + humanSize(master.sizeBytes) + ' · Version ' + master.version : '—'"></small>
            </div>
        </div>
        {{-- The file's own role, not a fixed label: the reference shows the role
             the file was actually stored under. --}}
        <div class="flex shrink-0 items-center gap-2">
            <a x-show="master && (master.renderedUrl || master.renderMode !== 'download')"
               x-bind:href="master ? (master.renderedUrl || master.previewUrl) : '#'" target="_blank" rel="noopener"
               class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                Open
            </a>
            <a x-show="master" x-bind:href="master ? master.downloadUrl : '#'"
               class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                Download
            </a>
            <x-ordo.pill tone="green" x-text="master ? (master.fileRole === 'master' ? 'Master File' : 'Supporting File') : 'Master File'" />
        </div>
    </div>

    <table class="mt-3 w-full border-separate border-spacing-0 overflow-hidden rounded-[9px] border border-ordo-line">
        <tr><td>Original Filename</td><td x-text="master ? master.name : '—'"></td></tr>
        <tr><td>Document Type</td><td x-text="master ? master.documentType : '—'"></td></tr>
        <tr><td>MIME Type</td><td x-text="master ? dash(master.mimeType) : '—'"></td></tr>
        <tr><td>Number of Pages</td><td x-text="master && master.pageCount ? master.pageCount : '—'"></td></tr>
        <tr><td>Total Files Stored</td><td x-text="current ? current.files.length : '—'"></td></tr>
        <tr><td>Total Storage</td><td x-text="current ? humanSize(current.storageBytes) : '—'"></td></tr>
        <tr><td>Document Reading Status</td><td x-text="master ? master.ocrStatusLabel : '—'"></td></tr>
        <tr><td>Integrity Hash</td><td class="break-all" x-text="master ? (master.checksum ?? 'Not available') : '—'"></td></tr>
        {{-- The reference carries the reference itself in the chip here rather than
             as plain text, so it reads as the same identifier in every place it
             appears in the drawer. --}}
        <tr><td>Document Reference</td><td>
            <span class="inline-flex items-center rounded-[7px] border border-ordo-blue-border bg-[#f4f7ff] px-2 py-[5px] font-mono text-[11px] font-bold tracking-[0.08em] text-[#315fce]"
                  x-text="current ? (current.documentReference ?? '—') : '—'"></span>
        </td></tr>
    </table>

    <div class="mt-3.5 mb-2 flex items-center justify-between gap-2">
        <h4 class="m-0 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Supporting Files</h4>
        <span class="text-[9px] text-ordo-muted" x-text="supportingFiles.length + ' file(s)'"></span>
    </div>

    <div class="flex flex-col gap-1.5">
        <template x-for="file in supportingFiles" x-bind:key="file.id">
            <div class="grid grid-cols-[1fr_auto] items-center gap-2.5 rounded-lg border border-ordo-line bg-white px-3 py-2.5">
                <div class="min-w-0">
                    <strong class="block truncate text-[11px]" x-text="file.name"></strong>
                    <small class="mt-0.5 block text-[8px] text-[#98a2b3]"
                            x-text="file.documentType + ' • ' + humanSize(file.sizeBytes) + (file.pageCount ? ' • ' + file.pageCount + ' page(s)' : '')"></small>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a x-show="file.renderedUrl || file.renderMode !== 'download'"
                       x-bind:href="file.renderedUrl || file.previewUrl" target="_blank" rel="noopener"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                        Open
                    </a>
                    <a x-bind:href="file.downloadUrl"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                        Download
                    </a>
                    <x-ordo.pill>Supporting</x-ordo.pill>
                </div>
            </div>
        </template>
        <template x-if="supportingFiles.length === 0">
            <x-ordo.empty message="No supporting files yet." />
        </template>
    </div>

    <div class="mt-3.5 mb-2 flex items-center justify-between gap-2">
        <h4 class="m-0 text-[10px] font-bold uppercase tracking-[0.04em] text-[#344054]">Version History</h4>
        <span class="text-[9px] text-ordo-muted">Same Record ID &middot; same Record Unit</span>
    </div>

    <div class="flex flex-col gap-1.5">
        <template x-for="file in versions" x-bind:key="file.id">
            <div class="grid grid-cols-[1fr_auto] items-center gap-2.5 rounded-lg border border-ordo-line bg-white px-3 py-2.5">
                <div class="min-w-0">
                    <strong class="block text-[11px]"
                            x-text="'Version ' + file.version + (file.isLatest ? ' — Current' : '')"></strong>
                    <small class="mt-0.5 block text-[8px] text-[#98a2b3]"
                            x-text="file.name + ' • ' + humanSize(file.sizeBytes) + ' • ' + (file.createdAt ?? '—')"></small>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a x-show="file.renderedUrl || file.renderMode !== 'download'"
                       x-bind:href="file.renderedUrl || file.previewUrl" target="_blank" rel="noopener"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                        Open
                    </a>
                    <a x-bind:href="file.downloadUrl"
                       class="rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px] text-[#374151] transition-colors hover:bg-[#f9fafb]">
                        Download
                    </a>
                    <x-ordo.pill x-bind:class="file.isLatest ? 'bg-[#e9f7f0] text-ordo-success' : 'bg-[#f3f4f6] text-[#4b5563]'">
                        <span x-text="file.isLatest ? 'Current' : 'Prior'"></span>
                    </x-ordo.pill>
                </div>
            </div>
        </template>
        <template x-if="versions.length === 0">
            <x-ordo.empty message="No version history yet." />
        </template>
    </div>

    {{-- The reference words this as "use Upload as Record only when…", and that
         control now exists above, so the wording can match. --}}
    <div class="mt-3 rounded-lg border border-dashed border-[#b8c7ef] bg-[#f8faff] px-3 py-2.5 text-[9px] leading-[1.55] text-[#475467]">
        Linking this record to another ORDO module or another Record never duplicates the master file.
        Use <b>Upload as Record</b> only when an attachment deserves its own independent Record ID
        and classification.
    </div>
</div>