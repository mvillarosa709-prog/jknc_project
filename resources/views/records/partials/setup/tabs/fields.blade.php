{{--
    Records Management Setup → Record Fields.

    The company's registry of extra fields it intends to capture beyond the
    standard record information.

    A registry, not a form builder: registering a field here does not yet add an
    input to the filing form. It records the decision so the scheme can be
    reviewed and agreed, and `Filterable` records the intent for when the wiring
    lands. Keeping it separate is what lets this be reviewed without touching the
    working filing flow.
--}}
<div x-data="{ adding: false }">

    @if ($canManage)
        <div class="mb-3">
            <x-ordo.button type="button" variant="primary"
                           x-on:click="adding = ! adding"
                           x-text="adding ? 'Cancel' : '+ Add Record Field'">+ Add Record Field</x-ordo.button>
        </div>
    @endif

    <form method="POST" action="{{ route('records.fields.store') }}"
          class="mb-3 rounded-xl border border-ordo-line bg-[#fbfcfe] p-3.5"
          x-show="adding" x-cloak>
        @csrf

        <div class="grid gap-3 max-[720px]:grid-cols-1 sm:grid-cols-[1fr_200px_140px_auto] sm:items-end">
            <x-ordo.field label="Field Name *" required>
                <input type="text" name="name" required
                       value="{{ old('name') }}"
                       placeholder="e.g. Client Code"
                       class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue" />
            </x-ordo.field>

            <x-ordo.field label="Field Type">
                <select name="type"
                        class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                    @foreach ($fieldTypes as $fieldType)
                        <option value="{{ $fieldType }}" @selected(old('type') === $fieldType)>{{ $fieldType }}</option>
                    @endforeach
                </select>
            </x-ordo.field>

            <x-ordo.field label="Filterable">
                <select name="is_filterable"
                        class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none focus:border-ordo-blue">
                    <option value="0" @selected(! old('is_filterable'))>No</option>
                    <option value="1" @selected((bool) old('is_filterable'))>Yes</option>
                </select>
            </x-ordo.field>

            <x-ordo.button type="submit" variant="primary">Add Field</x-ordo.button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-ordo-line bg-white">
        <table class="w-full border-collapse text-[12px]">
            <thead>
                <tr class="border-b border-ordo-line bg-[#fbfcfe] text-left">
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Field Name</th>
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Type</th>
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Filterable</th>
                    <th class="px-3 py-2.5 font-semibold text-ordo-muted">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($fields as $field)
                    <tr class="border-b border-ordo-line last:border-b-0">
                        <td class="px-3 py-2.5 text-ordo-ink">{{ $field->name }}</td>
                        <td class="px-3 py-2.5 text-ordo-muted">{{ $field->type->label() }}</td>
                        <td class="px-3 py-2.5 text-ordo-muted">{{ $field->is_filterable ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2.5">
                            <x-ordo.pill :tone="$field->status->label() === 'Active' ? 'green' : 'neutral'">
                                {{ $field->status->label() }}
                            </x-ordo.pill>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-ordo-faint">
                            No custom fields registered. The standard record information applies on its own.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 rounded-lg border border-ordo-line bg-[#fbfcfe] px-3 py-2.5 text-[10px] leading-[1.55] text-ordo-muted">
        <b>Scope note:</b> fields listed here are registered for the scheme. They are not yet
        added as inputs to the filing form — filing continues to capture the standard record
        information only.
    </p>
</div>
