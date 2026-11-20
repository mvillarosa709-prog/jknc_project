<x-layouts.app title="Unclassified Records" :unclassified-count="$unclassifiedCount ?? null">
    <div class="rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
        <x-ordo.page-head title="Upload Records"
                          subtitle="Upload first, then classify documents into the correct Classification Code and Index Code. Intake consumes storage only and does not use a Record Unit.">
            <x-slot:actions>
                <x-ordo.button variant="default" href="{{ route('records.unclassified') }}">&larr; Back to Unclassified Records</x-ordo.button>
            </x-slot:actions>
        </x-ordo.page-head>

        <div class="px-4 py-4">
            {{-- Status and validation errors both surface at the top of the page. --}}
            <x-ordo.flash />

            <div class="mb-4">
                <div class="mb-2.5 flex items-center justify-between">
                    <h2 class="m-0 text-[13px] font-bold">
                        Unclassified Records
                        <span class="ml-1 rounded-full bg-ordo-blue-soft px-[7px] py-[2px] align-middle text-[9px] text-ordo-blue">{{ $unclassifiedCount ?? 0 }}</span>
                    </h2>
                    @if ($intakeItems->total() > 0)
                        <span class="text-[10px] text-ordo-faint">Awaiting classification and filing</span>
                    @endif
                </div>

                @if ($intakeItems->isEmpty())
                    <x-ordo.empty message="No unclassified records yet. Uploaded documents appear here until they are filed." />
                @else
                    <div class="overflow-hidden rounded-xl border border-ordo-line bg-ordo-panel shadow-ordo">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-[#fbfcfe] text-[10px] font-semibold text-ordo-muted">
                                    <th class="px-3 py-2.5">Title</th>
                                    <th class="px-3 py-2.5">Files</th>
                                    <th class="px-3 py-2.5">Source</th>
                                    <th class="px-3 py-2.5">Document Date</th>
                                    <th class="px-3 py-2.5">Ext. Ref No.</th>
                                    <th class="px-3 py-2.5">Size</th>
                                    <th class="px-3 py-2.5">Record Units</th>
                                    <th class="px-3 py-2.5">Status</th>
                                    <th class="px-3 py-2.5">Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($intakeItems as $item)
                                    <tr @class([
                                        'border-b border-ordo-line text-[11px]',
                                        'bg-ordo-blue-soft' => $intake && $intake->id === $item->id,
                                    ])>
                                        <td class="px-3 py-2.5">
                                            <a class="block max-w-[280px] truncate font-semibold text-ordo-blue hover:underline"
                                               href="{{ route('records.intake.upload', ['intake' => $item->id]) }}">{{ $item->title }}</a>
                                            @foreach ($item->files->take(2) as $file)
                                                <small class="block max-w-[280px] truncate text-[9px] text-ordo-faint">{{ $file->original_name }}</small>
                                            @endforeach
                                            @if ($item->files->count() > 2)
                                                <small class="block text-[9px] text-ordo-faint">+{{ $item->files->count() - 2 }} more</small>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->files->count() }}</td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->source ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->document_date?->format('Y-m-d') ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->external_reference_number ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-ordo-muted">@fileSize($item->storage_bytes)</td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->recordUnits() }} (storage only)</td>
                                        <td class="px-3 py-2.5"><x-ordo.pill :tone="$item->status->tone()">{{ $item->status->label() }}</x-ordo.pill></td>
                                        <td class="px-3 py-2.5 text-ordo-muted">{{ $item->created_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($intakeItems->hasPages())
                        <div class="mt-2.5">{{ $intakeItems->links() }}</div>
                    @endif
                @endif
            </div>

            <form method="POST" action="{{ route('records.intake.store') }}" enctype="multipart/form-data"
                  x-on:ordo:camera-photo.window="acceptPhoto($event.detail)"
                  x-data="{
                      pending: [],
                      dragging: false,
                      capturedVia: 'upload',
                      acceptPhoto(file) {
                          const transfer = new DataTransfer();
                          transfer.items.add(file);
                          this.$refs.input.files = transfer.files;
                          this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                          this.capturedVia = 'camera';
                      },
                      get heading() {
                          return this.pending.length === 0
                              ? 'Drop files here to upload'
                              : this.pending.length + ' file(s) ready to upload';
                      },
                      read(files) {
                          this.pending = Array.from(files).map((f) => ({ name: f.name, size: f.size }));
                          if (this.capturedVia === 'camera') {
                              this.capturedVia = this.pending.length > 1 ? 'multi_upload' : 'upload';
                          }
                      },
                      human(bytes) {
                          const units = ['B', 'KB', 'MB', 'GB'];
                          let i = 0, n = bytes;
                          while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
                          return n.toFixed(i ? 1 : 0) + ' ' + units[i];
                      }
                  }">

                @csrf

                <input type="hidden" name="capture_source" x-bind:value="capturedVia" />

                <div class="rounded-xl border-[1.5px] border-dashed px-7 py-7 text-center transition-colors"
                     x-bind:class="dragging ? 'border-ordo-blue bg-[#eef4ff]' : 'border-[#b9c8ee] bg-[#f9fbff]'"
                     x-on:dragover.prevent="dragging = true"
                     x-on:dragleave.prevent="dragging = false"
                     x-on:drop.prevent="dragging = false; read($event.dataTransfer.files); $refs.input.files = $event.dataTransfer.files">

                    <strong class="mb-[5px] block text-[13px]" x-text="heading"></strong>
                    <span class="text-[10px] text-[#8b94a3]">
                        PDF, text, CSV, Word, Excel and images. You can upload multiple files at once.
                    </span>

                    <div class="mt-3 flex items-center justify-center gap-2">
                        <x-ordo.button type="button" variant="soft" x-on:click="$refs.input.click()">Choose Files</x-ordo.button>
                        <x-ordo.button type="button" variant="default"
                                       x-on:click="$dispatch('ordo:camera-open')">Capture from Camera</x-ordo.button>
                    </div>

                    <input type="file" name="files[]" multiple x-ref="input" class="hidden"
                           accept="{{ collect(config('ordo.document_extensions'))->map(fn ($ext) => ".{$ext}")->join(',') }}"
                           x-on:change="read($event.target.files)" />

                    <template x-if="pending.length">
                        <ul class="mx-auto mt-4 max-w-[520px] space-y-1 text-left">
                            <template x-for="file in pending" x-bind:key="file.name">
                                <li class="flex items-center gap-2 rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[10px]">
                                    <span class="min-w-0 flex-1 truncate text-[#475467]" x-text="file.name"></span>
                                    <span class="shrink-0 text-ordo-faint" x-text="human(file.size)"></span>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                <details class="mt-3 border-t border-ordo-line pt-2.5">
                    <summary class="cursor-pointer text-[11px] text-ordo-blue">Add Intake Details</summary>

                    <div class="mt-3 grid grid-cols-2 gap-2.5 max-[860px]:grid-cols-1">
                        <x-ordo.field label="Document Title" full
                                      help="Leave blank to use the first file name.">
                            <input type="text" name="title" maxlength="255"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Document Date">
                            <input type="date" name="document_date"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="External Reference No.">
                            <input type="text" name="external_reference_number" maxlength="255"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>

                        <x-ordo.field label="Source" full>
                            <input type="text" name="source" maxlength="255"
                                   placeholder="e.g. Securities and Exchange Commission"
                                   class="rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
                        </x-ordo.field>
                    </div>
                </details>

                <div class="mt-3 flex justify-end">
                    <x-ordo.button type="submit" variant="primary" x-bind:disabled="pending.length === 0">
                        Upload to Unclassified Records
                    </x-ordo.button>
                </div>

                @include('records.partials.camera-capture')
            </form>
        </div>
    </div>

    @if ($intake)
        <div class="mt-4 rounded-xl border border-ordo-line bg-ordo-panel p-4 shadow-ordo">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="m-0 text-[13px] font-bold">Intake Item Details</h2>
                <a href="{{ route('records.intake.upload') }}"
                   class="shrink-0 rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-[#374151] transition-colors hover:bg-[#f9fafb]">Close</a>
            </div>
            <dl class="grid grid-cols-2 gap-3 max-[860px]:grid-cols-1">
                <div>
                    <dt class="text-[9px] uppercase tracking-[0.05em] text-ordo-faint">Title</dt>
                    <dd class="mt-1 text-[12px]">{{ $intake->title }}</dd>
                </div>
                <div>
                    <dt class="text-[9px] uppercase tracking-[0.05em] text-ordo-faint">Capture Source</dt>
                    <dd class="mt-1"><x-ordo.pill tone="neutral">{{ $intake->capture_source->label() }}</x-ordo.pill></dd>
                </div>
                <div>
                    <dt class="text-[9px] uppercase tracking-[0.05em] text-ordo-faint">Status</dt>
                    <dd class="mt-1"><x-ordo.pill :tone="$intake->status->tone()">{{ $intake->status->label() }}</x-ordo.pill></dd>
                </div>
                <div>
                    <dt class="text-[9px] uppercase tracking-[0.05em] text-ordo-faint">Record Units</dt>
                    <dd class="mt-1 text-[12px]">{{ $intake->recordUnits() }} (storage only)</dd>
                </div>
            </dl>

            <table class="mt-4 w-full border-collapse text-left">
                <thead>
                    <tr class="bg-[#fbfcfe] text-[10px] font-semibold text-ordo-muted">
                        <th class="sticky top-0 px-3 py-2.5">File</th>
                        <th class="px-3 py-2.5">Type</th>
                        <th class="px-3 py-2.5">Pages</th>
                        <th class="px-3 py-2.5">Size</th>
                        <th class="px-3 py-2.5">Auto Read</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($intake->files as $file)
                        <tr class="border-b border-ordo-line text-[11px]">
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="grid size-[34px] shrink-0 place-items-center rounded-md border border-ordo-line bg-[#f3f4f6] text-[10px] text-ordo-muted">
                                        {{ $file->isPdf() ? 'PDF' : ($file->isImage() ? 'IMG' : 'FILE') }}
                                    </div>
                                    <div class="min-w-0">
                                        <strong class="block max-w-[260px] truncate text-[11px]">{{ $file->original_name }}</strong>
                                        <small class="text-[9px] text-ordo-faint">{{ $file->documentType() }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-ordo-muted">{{ $file->mime_type ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-ordo-muted">{{ $file->page_count ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-ordo-muted">@fileSize($file->size_bytes)</td>
                            <td class="px-3 py-2.5">
                                <x-ordo.pill :tone="$file->ocr_status->tone()">{{ $file->ocr_status->label() }}</x-ordo.pill>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
