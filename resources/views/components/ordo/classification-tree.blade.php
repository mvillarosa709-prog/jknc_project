@props(['classifications', 'canManage' => false])

<div class="space-y-3.5 py-1.5">
    @forelse ($classifications as $classification)
        <div x-show="matches(textFor(@js($classification->code)))"
             class="overflow-hidden rounded-xl border border-ordo-line bg-white shadow-xs transition-shadow hover:shadow-sm">

            {{-- Classification Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ordo-line bg-[#f8faff] px-4 py-3 max-[640px]:flex-col max-[640px]:items-start">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="inline-flex shrink-0 items-center rounded-lg border border-blue-200/80 bg-blue-50 px-2.5 py-1 font-mono text-[13px] font-bold tracking-wide text-[#2b59c3]">
                        {{ $classification->code }}
                    </span>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[14px] font-bold text-ordo-ink truncate">{{ $classification->name }}</span>
                            <x-ordo.pill :tone="$classification->is_active ? 'green' : 'neutral'">
                                {{ $classification->is_active ? 'Active' : 'Inactive' }}
                            </x-ordo.pill>
                        </div>
                        @if ($classification->description)
                            <div class="mt-0.5 text-[11px] leading-relaxed text-[#6b7280]">
                                {{ $classification->description }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Classification Actions --}}
                <div class="flex items-center gap-2 max-[640px]:w-full max-[640px]:justify-end">
                    @if ($canManage)
                        <button type="button"
                                x-on:click="openEditClassification({
                                    id: @js($classification->id),
                                    code: @js($classification->code),
                                    name: @js($classification->name),
                                    description: @js($classification->description),
                                    is_active: @js($classification->is_active)
                                })"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-ordo-line bg-white px-2.5 py-1.5 text-[11px] font-medium text-ordo-ink shadow-xs transition-colors hover:bg-[#f3f4f6]">
                            <svg class="h-3 w-3 text-ordo-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Edit
                        </button>

                        <button type="button"
                                x-on:click="open('index', @js($classification->code))"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-ordo-blue px-3 py-1.5 text-[11px] font-semibold text-white shadow-xs transition-colors hover:bg-[#2753b8]">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Index
                        </button>
                    @endif
                </div>
            </div>

            {{-- Children: Indexes List --}}
            <div class="divide-y divide-ordo-line bg-white">
                @forelse ($classification->indexes as $index)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 pl-6 transition-colors hover:bg-[#fbfcfe] max-[640px]:flex-col max-[640px]:items-start max-[640px]:pl-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex shrink-0 items-center rounded-md border border-ordo-line bg-[#f8f9fa] px-2 py-0.5 font-mono text-[11px] font-medium text-[#4b5563]">
                                {{ $index->code }}
                            </span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[12px] font-semibold text-ordo-ink">{{ $index->name }}</span>
                                    <x-ordo.pill :tone="$index->is_active ? 'green' : 'neutral'">
                                        {{ $index->is_active ? 'Active' : 'Inactive' }}
                                    </x-ordo.pill>
                                </div>
                                @if ($index->description)
                                    <div class="text-[10px] leading-relaxed text-[#6b7280]">
                                        {{ $index->description }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Index Actions --}}
                        <div class="flex items-center gap-3.5 max-[640px]:w-full max-[640px]:justify-end">
                            @if ($canManage)
                                <button type="button"
                                        x-on:click="openEditIndex({
                                            id: @js($index->id),
                                            code: @js($index->code),
                                            name: @js($index->name),
                                            description: @js($index->description),
                                            is_active: @js($index->is_active)
                                        }, @js($classification->code))"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-ordo-muted transition-colors hover:text-ordo-blue">
                                    <svg class="h-3 w-3 text-ordo-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    Edit
                                </button>
                            @endif

                            <a href="{{ route('records.index', ['classification' => $classification->code, 'index' => $index->code]) }}"
                               class="inline-flex items-center gap-1 text-[11px] font-medium text-ordo-blue transition-opacity hover:opacity-75">
                                <span>View Records</span>
                                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-3 text-[11px] text-ordo-faint italic">
                        No indexes under this classification yet.
                    </div>
                @endforelse
            </div>
        </div>
    @empty
        <x-ordo.empty message="No classifications have been added for this company yet." />
    @endforelse
</div>
