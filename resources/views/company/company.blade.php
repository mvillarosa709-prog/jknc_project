@extends('layouts.app')
@section('title', 'Company')

@section('content')
@php
    $authUser = auth()->user();
    $canDeleteRecords = $authUser
        && ($authUser->isAdmin()
            || $authUser->isSuperAdmin()
            || $authUser->hasPermission('approve_corporate')
            || $authUser->hasPermission('access_admin_dashboard'));
@endphp
<div class="px-6 py-6 lg:px-8">
    <div class="mb-5">
        <h1 class="text-3xl font-semibold text-gray-900">Company</h1>
        <p class="mt-1 text-sm text-gray-500">Manage companies and deal relationships</p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('company.index') }}" class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative w-full max-w-md">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search Company..."
                class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
        </div>

        <select
            name="type"
            class="h-10 min-w-[180px] rounded-lg border border-gray-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            onchange="this.form.submit()"
        >
            <option value="All" {{ $typeFilter === 'All' ? 'selected' : '' }}>All</option>
            @foreach ($companyTypes as $companyType)
                <option value="{{ $companyType }}" {{ $typeFilter === $companyType ? 'selected' : '' }}>{{ $companyType }}</option>
            @endforeach
        </select>

        <input type="hidden" name="per_page" value="{{ $perPage }}">

        <button
            type="button"
            id="openAddCompanyModal"
            class="ml-auto h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700"
        >
            + Add Company
        </button>
    </form>

    <div id="selectionActionBar" class="mb-3 hidden rounded-lg border border-blue-100 bg-blue-50 px-3 py-2">
        <div class="flex items-center gap-2 text-sm">
            <span class="font-medium text-gray-800"><span id="selectedCount">0</span> selected</span>
            <button type="button" class="h-8 rounded-md border border-gray-200 bg-white px-3 hover:bg-gray-50">Assign Owner</button>
            @if ($canDeleteRecords)
                <button id="openDeleteSelectedModal" type="button" class="h-8 rounded-md border border-red-200 bg-white px-3 text-red-600 hover:bg-red-50">Delete Selected</button>
            @endif
            <button type="button" id="clearSelection" class="ml-auto text-gray-700 hover:underline">Clear</button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="w-10 px-3 py-3 text-left"><input id="selectAll" type="checkbox" class="h-4 w-4 rounded border-gray-300"></th>
                        <th class="px-3 py-3 text-left">Company Name</th>
                        <th class="px-3 py-3 text-left">Phone / Mobile</th>
                        <th class="px-3 py-3 text-left">TIN</th>
                        <th class="px-3 py-3 text-left">Status</th>
                        <th class="px-3 py-3 text-left">Company Owner</th>
                        <th class="px-3 py-3 text-left">Actions</th>
                        @foreach ($customFields as $field)
                            <th class="px-3 py-3 text-left">{{ $field['name'] }}</th>
                        @endforeach
                        <th class="px-3 py-3 text-right normal-case">
                            <button id="openCreateFieldDropdown" type="button" class="text-sm font-medium text-blue-600 hover:text-blue-700">+ Create Field</button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($companies as $company)
                        @php
                            $companyName = data_get($company, 'company_name', '');
                            $companyPhone = data_get($company, 'phone');
                            $companyMobile = data_get($company, 'mobile_no');
                            $companyTin = data_get($company, 'tin_no');
                            $companyStatus = data_get($company, 'status', 'draft');
                            $companyOwner = data_get($company, 'owner_name', 'Owner 1');
                            $initials = collect(explode(' ', (string) $company->company_name))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part) => mb_substr($part, 0, 1))
                                ->implode('');
                            $initials = strtoupper($initials !== '' ? $initials : mb_substr((string) $companyName, 0, 2));
                        @endphp
                        <tr class="text-gray-700 hover:bg-gray-50">
                            <td class="px-3 py-3"><input type="checkbox" value="{{ $company->id }}" class="row-checkbox h-4 w-4 rounded border-gray-300"></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">{{ $initials }}</div>
                                    <a href="{{ route('company.kyc', ['company' => $company->id, 'tab' => 'business-client-information']) }}" class="font-medium text-gray-900 hover:text-blue-700">
                                        {{ $companyName }}
                                    </a>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ $companyPhone ?: ($companyMobile ?: '-') }}</td>
                            <td class="px-3 py-3">{{ $companyTin ?: '-' }}</td>
                            <td class="px-3 py-3">
                                @php
                                    $statusClasses = match ($companyStatus) {
                                        'approved' => 'bg-green-100 text-green-700 border border-green-200',
                                        'pending_approval' => 'bg-amber-100 text-amber-700 border border-amber-200',
                                        'inactive', 'rejected' => 'bg-red-100 text-red-700 border border-red-200',
                                        default => 'bg-gray-100 text-gray-700 border border-gray-200',
                                    };
                                    $statusLabel = str_replace('_', ' ', ucfirst((string) $companyStatus));
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-3 py-3">{{ $companyOwner ?: 'Owner 1' }}</td>
                            <td class="px-3 py-3">
                                <a href="{{ route('company.kyc', ['company' => $company->id, 'tab' => 'business-client-information']) }}" class="inline-flex h-8 items-center rounded-full border border-gray-200 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    View
                                </a>
                            </td>
                            @foreach ($customFields as $field)
                                @php
                                    $customValue = $company->custom_fields[$field['key']] ?? ($field['default_value'] ?? '');
                                @endphp
                                <td class="px-3 py-3 text-gray-600">
                                    @if (($field['type'] ?? '') === 'checkbox')
                                        {{ $customValue === '1' ? 'Yes' : 'No' }}
                                    @elseif (($field['type'] ?? '') === 'currency' && $customValue !== '')
                                        P{{ number_format((float) $customValue, 2) }}
                                    @else
                                        {{ $customValue !== '' ? $customValue : '-' }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-3"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 7 + count($customFields) }}" class="px-3 py-10 text-center text-sm text-gray-500">No companies found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-gray-700">
        <span>Total Companies: {{ $companies->total() }}</span>
        @foreach ($typeCounts as $label => $count)
            <span>{{ $label }}: <span class="text-gray-900">{{ $count }}</span></span>
        @endforeach
        <div class="ml-auto flex items-center gap-2 text-xs text-gray-600">
            <form method="GET" action="{{ route('company.index') }}" class="flex items-center gap-2">
                <span>Rows</span>
                <select name="per_page" class="h-7 rounded border border-gray-200 px-2 text-xs" onchange="this.form.submit()">
                    @foreach ([5, 10, 25, 50] as $size)
                        <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="type" value="{{ $typeFilter }}">
            </form>
            <span>{{ $companies->firstItem() ?? 0 }}-{{ $companies->lastItem() ?? 0 }} of {{ $companies->total() }}</span>
        </div>
    </div>
</div>

@if ($canDeleteRecords)
    <div id="deleteSelectedModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">
        <button id="deleteSelectedOverlay" type="button" aria-label="Close delete companies modal" class="absolute inset-0 bg-slate-900/45"></button>
        <div class="absolute inset-0 flex items-center justify-center px-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h2 class="text-xl font-semibold text-gray-900">Delete Selected Companies</h2>
                    <p class="mt-1 text-sm text-gray-500">This action will permanently delete the selected company records.</p>
                </div>
                <form id="bulkDeleteForm" method="POST" action="{{ route('company.bulk-delete') }}">
                    @csrf
                    @method('DELETE')
                    <div id="bulkDeleteSelectedCompanies"></div>
                    <div class="px-6 py-5 text-sm text-gray-700">
                        Are you sure you want to delete <span id="bulkDeleteCountText" class="font-semibold text-gray-900">0 companies</span>?
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                        <button id="cancelDeleteSelectedModal" type="button" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="h-10 rounded-lg bg-red-600 px-5 text-sm font-medium text-white hover:bg-red-700">Delete Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@include('company.partials.modal-add-company')
@include('products.partials.create-field-dropdown', [
    'fieldTypes' => $fieldTypes,
    'dropdownId' => 'createFieldDropdownMenu',
    'extraOptions' => [
        [
            'value' => 'report',
            'label' => 'REPORT',
            'icon' => 'fa-file-lines',
        ],
    ],
])
@include('products.partials.create-field-modal', [
    'createFieldActionRoute' => route('company.custom-fields.store'),
    'lookupModules' => $lookupModules,
])

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addCompanyModal');
    const openButton = document.getElementById('openAddCompanyModal');
    const closeButtons = document.querySelectorAll('[data-close-company-modal]');
    const openCreateFieldDropdown = document.getElementById('openCreateFieldDropdown');
    const createFieldDropdownMenu = document.getElementById('createFieldDropdownMenu');
    const fieldTypeButtons = Array.from(document.querySelectorAll('.create-field-type-option'));
    const companyReportOption = document.querySelector('[data-special-action="report"]');
    const createFieldModal = document.getElementById('createFieldModal');
    const createFieldPanel = document.getElementById('createFieldPanel');
    const createFieldModalOverlay = document.getElementById('createFieldModalOverlay');
    const closeCreateFieldModal = document.getElementById('closeCreateFieldModal');
    const cancelCreateFieldModal = document.getElementById('cancelCreateFieldModal');
    const createFieldTypeInput = document.getElementById('createFieldTypeInput');
    const createFieldTypeLabel = document.getElementById('createFieldTypeLabel');
    const picklistOptionsSection = document.getElementById('picklistOptionsSection');
    const picklistOptionsContainer = document.getElementById('picklistOptionsContainer');
    const addPicklistOption = document.getElementById('addPicklistOption');
    const defaultValueSection = document.getElementById('defaultValueSection');
    const lookupSection = document.getElementById('lookupSection');
    const defaultValueInput = document.getElementById('default_value');
    const selectAll = document.getElementById('selectAll');
    const rowChecks = Array.from(document.querySelectorAll('.row-checkbox'));
    const actionBar = document.getElementById('selectionActionBar');
    const selectedCount = document.getElementById('selectedCount');
    const clearSelection = document.getElementById('clearSelection');
    const deleteSelectedModal = document.getElementById('deleteSelectedModal');
    const deleteSelectedOverlay = document.getElementById('deleteSelectedOverlay');
    const openDeleteSelectedModalButton = document.getElementById('openDeleteSelectedModal');
    const cancelDeleteSelectedModalButton = document.getElementById('cancelDeleteSelectedModal');
    const bulkDeleteSelectedCompanies = document.getElementById('bulkDeleteSelectedCompanies');
    const bulkDeleteCountText = document.getElementById('bulkDeleteCountText');
    let createFieldDropdownOpen = false;

    const openModal = () => {
        if (!modal) {
            return;
        }

        window.jkncSlideOver.open(modal);
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        window.jkncSlideOver.close(modal);
    };

    const buildPicklistOptionRow = (value = '') => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.innerHTML = `
            <input name="options[]" value="${value}" placeholder="Option value" class="h-10 flex-1 rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            <button type="button" class="remove-picklist-option h-8 w-8 rounded-full border border-gray-300 text-gray-500 hover:bg-gray-50">
                <i class="fas fa-minus text-xs"></i>
            </button>
        `;

        return row;
    };

    const ensurePicklistOptionRows = () => {
        if (!picklistOptionsContainer) {
            return;
        }

        if (picklistOptionsContainer.querySelectorAll('input[name="options[]"]').length === 0) {
            picklistOptionsContainer.appendChild(buildPicklistOptionRow(''));
        }
    };

    const applyCreateFieldTypeUI = (type, label) => {
        if (!createFieldTypeInput || !createFieldTypeLabel) {
            return;
        }

        createFieldTypeInput.value = type;
        createFieldTypeLabel.textContent = label;
        picklistOptionsSection?.classList.toggle('hidden', type !== 'picklist');
        lookupSection?.classList.toggle('hidden', type !== 'lookup');
        lookupSection?.classList.toggle('grid', type === 'lookup');
        defaultValueSection?.classList.toggle('hidden', type === 'lookup');
        defaultValueSection?.classList.toggle('grid', type !== 'lookup');

        if (defaultValueInput) {
            defaultValueInput.placeholder = type === 'date' ? 'YYYY-MM-DD' : 'Optional default value';
        }

        if (type === 'picklist') {
            ensurePicklistOptionRows();
        }
    };

    const openCreateFieldModalFn = (type, label) => {
        applyCreateFieldTypeUI(type, label);
        createFieldDropdownMenu?.classList.add('hidden');
        createFieldDropdownOpen = false;
        createFieldModal?.classList.remove('hidden');
        createFieldModal?.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(() => {
            createFieldModalOverlay?.classList.remove('opacity-0');
            createFieldPanel?.classList.remove('translate-x-full');
        });
    };

    const closeCreateFieldModalFn = () => {
        createFieldModalOverlay?.classList.add('opacity-0');
        createFieldPanel?.classList.add('translate-x-full');
        document.body.classList.remove('overflow-hidden');
        window.setTimeout(() => {
            createFieldModal?.classList.add('hidden');
            createFieldModal?.setAttribute('aria-hidden', 'true');
        }, 300);
    };

    const positionCreateFieldDropdown = () => {
        if (!openCreateFieldDropdown || !createFieldDropdownMenu) {
            return;
        }

        const rect = openCreateFieldDropdown.getBoundingClientRect();
        const dropdownWidth = createFieldDropdownMenu.offsetWidth || 256;
        const viewportPadding = 12;

        let left = rect.left;
        if (left + dropdownWidth > window.innerWidth - viewportPadding) {
            left = rect.right - dropdownWidth;
        }
        if (left < viewportPadding) {
            left = viewportPadding;
        }

        let top = rect.bottom + 8;
        const dropdownHeight = createFieldDropdownMenu.offsetHeight || 320;
        if (top + dropdownHeight > window.innerHeight - viewportPadding) {
            top = Math.max(viewportPadding, rect.top - dropdownHeight - 8);
        }

        createFieldDropdownMenu.style.left = `${left}px`;
        createFieldDropdownMenu.style.top = `${top}px`;
    };

    const openCreateFieldDropdownFn = () => {
        if (!createFieldDropdownMenu) {
            return;
        }

        createFieldDropdownMenu.classList.remove('hidden');
        createFieldDropdownOpen = true;
        positionCreateFieldDropdown();
    };

    const closeCreateFieldDropdownFn = () => {
        createFieldDropdownMenu?.classList.add('hidden');
        createFieldDropdownOpen = false;
    };

    const closeDeleteSelectedModal = () => {
        if (!deleteSelectedModal) {
            return;
        }

        deleteSelectedModal.classList.add('hidden');
        deleteSelectedModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    };

    const openDeleteSelectedModal = () => {
        const selectedCompanies = rowChecks.filter((item) => item.checked);
        if (selectedCompanies.length === 0 || !deleteSelectedModal) {
            return;
        }

        if (bulkDeleteSelectedCompanies) {
            bulkDeleteSelectedCompanies.innerHTML = selectedCompanies
                .map((item) => `<input type="hidden" name="selected_companies[]" value="${item.value}">`)
                .join('');
        }

        if (bulkDeleteCountText) {
            bulkDeleteCountText.textContent = `${selectedCompanies.length} ${selectedCompanies.length === 1 ? 'company' : 'companies'}`;
        }

        deleteSelectedModal.classList.remove('hidden');
        deleteSelectedModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    const refreshSelection = () => {
        const count = rowChecks.filter((item) => item.checked).length;
        selectedCount.textContent = count;
        actionBar.classList.toggle('hidden', count === 0);

        if (selectAll) {
            selectAll.checked = rowChecks.length > 0 && count === rowChecks.length;
        }
    };

    openButton?.addEventListener('click', openModal);

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal?.addEventListener('click', function (event) {
        if (event.target === modal || event.target.hasAttribute('data-drawer-overlay')) {
            closeModal();
        }
    });

    openCreateFieldDropdown?.addEventListener('click', function () {
        if (createFieldDropdownOpen) {
            closeCreateFieldDropdownFn();
            return;
        }

        openCreateFieldDropdownFn();
    });

    fieldTypeButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const type = button.dataset.fieldType || 'picklist';
            const label = button.dataset.fieldLabel || 'Picklist';
            openCreateFieldModalFn(type, label);
        });
    });

    companyReportOption?.addEventListener('click', function () {
        const selectedCompanies = Array.from(
            document.querySelectorAll('.row-checkbox:checked')
        ).map((checkbox) => checkbox.value);

        if (selectedCompanies.length !== 1) {
            alert('Please select exactly one company to generate a report.');
            return;
        }

        closeCreateFieldDropdownFn();

        window.location.href =
            @json(url('/company')) +
            '/' +
            encodeURIComponent(selectedCompanies[0]) +
            '/report';
    });

    closeCreateFieldModal?.addEventListener('click', closeCreateFieldModalFn);
    cancelCreateFieldModal?.addEventListener('click', closeCreateFieldModalFn);
    createFieldModalOverlay?.addEventListener('click', closeCreateFieldModalFn);

    document.addEventListener('click', function (event) {
        if (createFieldDropdownMenu && createFieldDropdownOpen) {
            const clickedFieldTrigger = openCreateFieldDropdown ? openCreateFieldDropdown.contains(event.target) : false;
            if (!createFieldDropdownMenu.contains(event.target) && !clickedFieldTrigger) {
                closeCreateFieldDropdownFn();
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
            closeCreateFieldDropdownFn();
            closeCreateFieldModalFn();
            closeDeleteSelectedModal();
        }
    });

    window.addEventListener('resize', function () {
        if (createFieldDropdownOpen) {
            positionCreateFieldDropdown();
        }
    });

    document.addEventListener('scroll', function () {
        if (createFieldDropdownOpen) {
            positionCreateFieldDropdown();
        }
    }, true);

    addPicklistOption?.addEventListener('click', function () {
        picklistOptionsContainer?.appendChild(buildPicklistOptionRow(''));
    });

    picklistOptionsContainer?.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-picklist-option');
        if (!button) {
            return;
        }

        button.closest('.flex')?.remove();
        ensurePicklistOptionRows();
    });

    selectAll?.addEventListener('change', function () {
        rowChecks.forEach((check) => { check.checked = selectAll.checked; });
        refreshSelection();
    });

    rowChecks.forEach((check) => check.addEventListener('change', refreshSelection));
    clearSelection?.addEventListener('click', function () {
        rowChecks.forEach((check) => { check.checked = false; });
        if (selectAll) {
            selectAll.checked = false;
        }
        refreshSelection();
    });

    openDeleteSelectedModalButton?.addEventListener('click', openDeleteSelectedModal);
    cancelDeleteSelectedModalButton?.addEventListener('click', closeDeleteSelectedModal);
    deleteSelectedOverlay?.addEventListener('click', closeDeleteSelectedModal);

    const initialFieldType = createFieldTypeInput ? createFieldTypeInput.value : 'picklist';
    const initialTypeButton = fieldTypeButtons.find((button) => (button.dataset.fieldType || '') === initialFieldType);
    applyCreateFieldTypeUI(initialFieldType, initialTypeButton?.dataset.fieldLabel || 'Picklist');

    @if ($errors->any())
        @if (old('field_type'))
            openCreateFieldModalFn('{{ old('field_type') }}', '{{ $fieldTypes->firstWhere('value', old('field_type'))['label'] ?? 'Picklist' }}');
        @else
            openModal();
        @endif
    @elseif (request()->boolean('open_create_company') || request()->has('company_contact_created'))
        openModal();
    @endif
});
</script>
@endsection
