{{--
    The Classification scheme as a two-level tree.

    One continuous list rather than a card per Classification, so the rows read as
    one structure. The four-column grid is what makes a parent row and the Indexes
    beneath it line up: the status column is fixed width so every pill starts in
    the same place, and the action column is fixed and right-aligned so every link
    ends in the same place. Measured off the reference stylesheet.

    Rendered by both the Classifications page and the Records Management Setup
    modal. They show the same scheme, so they share this component rather than
    each carrying a copy that can drift from the other.

    Search is not handled here: the enclosing panel owns the browser component
    that hides a whole block when the term misses, so a Classification is never
    left on screen without the Indexes that matched.
--}}
@props(['classifications', 'canManage' => false])

<div class="py-1.5">
    @forelse ($classifications as $classification)
        <div x-show="matches(textFor(@js($classification->code)))">

            {{-- Parent: the Classification itself.

                 The code and name are set at 16px deliberately, not by accident.
                 The reference mock's .class-row sets a font size for the
                 description, the action and the pill, but never for the row
                 itself, so its primary text inherits the browser default of
                 16px. Stating the measured value here rather than leaving it to
                 inheritance keeps it visible and stops a later tidy-up from
                 quietly shrinking the scheme back down to body scale.

                 The code is proportional, matching the mock: it puts no
                 font-family on .class-row. The monospace face is reserved for
                 the Document Reference chip, which is where the mock does use
                 it. --}}
            <div class="grid grid-cols-[90px_1fr_120px_90px] items-center gap-2.5 border-b border-ordo-line bg-[#fbfcfe] px-3 py-2.5 font-bold max-[640px]:grid-cols-1">
                <span class="text-[16px] text-ordo-ink">{{ $classification->code }}</span>

                <div class="min-w-0">
                    <span class="text-[16px]">{{ $classification->name }}</span>
                    @if ($classification->description)
                        {{-- Weight is reset here: the parent row is bold, but a
                             description set in bold reads as a second heading. --}}
                        <div class="mt-0.5 font-normal text-[10px] leading-[1.5] text-[#6b7280]">
                            {{ $classification->description }}
                        </div>
                    @endif
                </div>

                <x-ordo.pill :tone="$classification->is_active ? 'green' : 'neutral'">
                    {{ $classification->is_active ? 'Active' : 'Inactive' }}
                </x-ordo.pill>

                <div class="text-right">
                    @if ($canManage)
                        <button type="button"
                                x-on:click="open('index', @js($classification->code))"
                                class="border-0 bg-transparent p-0 text-[11px] text-ordo-blue transition-opacity hover:opacity-75">
                            + Add Index
                        </button>
                    @endif
                </div>
            </div>

            {{-- Children: the Indexes beneath it. Same 16px as the Classification
                     above and the same proportional code — one scheme, one
                     scale. --}}
            @forelse ($classification->indexes as $index)
                <div class="grid grid-cols-[90px_1fr_120px_90px] items-center gap-2.5 border-b border-ordo-line px-3 py-2.5 pl-[36px] max-[640px]:grid-cols-1 max-[640px]:pl-3">
                    <span class="text-[16px] text-ordo-ink">{{ $index->code }}</span>

                    <div class="min-w-0">
                        <span class="text-[16px]">{{ $index->name }}</span>
                        @if ($index->description)
                            <div class="mt-0.5 text-[10px] leading-[1.5] text-[#6b7280]">
                                {{ $index->description }}
                            </div>
                        @endif
                    </div>

                    <x-ordo.pill :tone="$index->is_active ? 'green' : 'neutral'">
                        {{ $index->is_active ? 'Active' : 'Inactive' }}
                    </x-ordo.pill>

                    <div class="text-right">
                        <a href="{{ route('records.index', ['classification' => $classification->code, 'index' => $index->code]) }}"
                           class="text-[11px] text-ordo-blue transition-opacity hover:opacity-75">View Records</a>
                    </div>
                </div>
            @empty
                <div class="border-b border-ordo-line px-3 py-2.5 pl-[36px] text-[10px] text-ordo-faint max-[640px]:pl-3">
                    No indexes under this classification yet.
                </div>
            @endforelse
        </div>
    @empty
        <x-ordo.empty message="No classifications have been added for this company yet." />
    @endforelse
</div>
