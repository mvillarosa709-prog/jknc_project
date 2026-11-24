@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3">
        <div class="text-[10px] text-ordo-faint">
            Showing <span class="font-semibold text-ordo-muted">{{ $paginator->firstItem() }}</span>&ndash;<span class="font-semibold text-ordo-muted">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold text-ordo-muted">{{ $paginator->total() }}</span>
        </div>

        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-ordo-line bg-[#f3f4f6] px-3 py-2 text-[12px] text-ordo-faint opacity-60">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex items-center justify-center rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-[#374151] transition-colors hover:bg-[#f9fafb]">Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex items-center justify-center rounded-lg border border-ordo-line bg-ordo-panel px-3 py-2 text-[12px] text-[#374151] transition-colors hover:bg-[#f9fafb]">Next</a>
            @else
                <span class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-ordo-line bg-[#f3f4f6] px-3 py-2 text-[12px] text-ordo-faint opacity-60">Next</span>
            @endif
        </div>
    </nav>
@endif
