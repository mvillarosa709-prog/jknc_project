{{--
    Records Management Setup → Sources.

    A Source is the company or office a document came from. The list is
    maintained here rather than on a screen of its own, so the setup modal is the
    one place the scheme's reference data lives.

    The add form is a plain post, so a rejected name comes back with Laravel's
    validation messages rather than being re-typed into a client-side form.
--}}
<div x-data="{ adding: false }">

    @if ($canManage)
        <div class="mb-3">
            <x-ordo.button type="button" variant="primary"
                           x-on:click="adding = ! adding"
                           x-text="adding ? 'Cancel' : '+ Add Source'">+ Add Source</x-ordo.button>
        </div>
    @endif

    <form method="POST" action="{{ route('records.sources.store') }}"
          class="mb-3 rounded-xl border border-ordo-line bg-[#fbfcfe] p-3.5"
          x-show="adding" x-cloak>
        @csrf

        <div class="grid gap-3 max-[720px]:grid-cols-1 sm:grid-cols-[1fr_220px_auto] sm:items-end">
            <x-ordo.field label="Source Name *" required>
                <input type="text" name="name" required
                       value="{{ old('name') }}"
                       placeholder="e.g. Bureau of Internal Revenue"
                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
            </x-ordo.field>

            <x-ordo.field label="Source Type">
                <select name="type"
                        class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                    @foreach ($sourceTypes as $sourceType)
                        <option value="{{ $sourceType }}" @selected(old('type') === $sourceType)>{{ $sourceType }}</option>
                    @endforeach
                </select>
            </x-ordo.field>

            <x-ordo.button type="submit" variant="primary">Add Source</x-ordo.button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-ordo-line bg-white">
        <table class="w-full border-collapse text-[12px]">
            <thead>
                <tr class="border-b border-ordo-line bg-[#fbfcfe] text-left">
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Source Name</th>
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Type</th>
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sources as $source)
                    <tr class="border-b border-ordo-line last:border-b-0">
                        <td class="px-3 py-2.5 text-ordo-ink">{{ $source->name }}</td>
                        <td class="px-3 py-2.5 text-ordo-muted">{{ $source->type->label() }}</td>
                        <td class="px-3 py-2.5">
                            <x-ordo.pill :tone="$source->status->label() === 'Active' ? 'green' : 'neutral'">
                                {{ $source->status->label() }}
                            </x-ordo.pill>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-6 text-center text-ordo-faint">
                            No sources yet. Add the offices and agencies your records come from.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
