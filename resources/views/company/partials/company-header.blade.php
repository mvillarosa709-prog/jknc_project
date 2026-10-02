@php
    $authUser = auth()->user();
    $canDeleteRecords = $authUser
        && ($authUser->isAdmin()
            || $authUser->isSuperAdmin()
            || $authUser->hasPermission('approve_corporate')
            || $authUser->hasPermission('access_admin_dashboard'));
@endphp
<div id="companyHeaderWrap" class="border-b border-gray-100 px-4 py-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <a
                    href="{{ route('company.index') }}"
                    class="inline-flex items-center gap-1 text-gray-600 hover:text-gray-900"
                >
                    <i class="fas fa-arrow-left text-xs"></i>
                    <span>Company</span>
                </a>
                <span>/</span>
                <span id="companyHeaderBreadcrumb" class="font-semibold text-gray-900 truncate">{{ $company->company_name }}</span>
            </div>

            <div id="companyHeaderSummary" class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-1 flex-wrap items-start gap-5">
                        <div class="h-16 w-16 shrink-0 rounded-lg border border-gray-200 bg-gray-100 text-gray-600 flex items-center justify-center text-sm font-bold leading-tight">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($company->company_name, 0, 2)) }}
                        </div>

                        <div class="min-w-[240px] flex-1">
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $company->company_name }}</h1>
                            @if (! empty($company->company_type))
                                <p class="mt-1 text-sm text-gray-500">{{ $company->company_type }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                                @if (! empty($company->address))
                                    <p><span class="font-medium text-gray-700">Address:</span> {{ $company->address }}</p>
                                @endif
                                @if (! empty($company->bif_no ?? null))
                                    <p><span class="font-medium text-gray-700">BIF Number:</span> {{ $company->bif_no }}</p>
                                @endif
                                @if (! empty($company->phone))
                                    <p><span class="font-medium text-gray-700">Phone:</span> {{ $company->phone }}</p>
                                @endif
                                @if (! empty($company->website))
                                    <p>
                                        <span class="font-medium text-gray-700">Website:</span>
                                        <a href="{{ $company->website }}" class="text-blue-600 underline" target="_blank" rel="noreferrer">{{ $company->website }}</a>
                                    </p>
                                @endif
                                @if (! empty($companyKycStatus ?? null))
                                    <p class="flex items-center gap-2">
                                        <span class="font-medium text-gray-700">Status:</span>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $companyKycStatusClass ?? 'bg-gray-100 text-gray-600 border border-gray-200' }}">
                                            {{ $companyKycStatus }}
                                        </span>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            id="openEditCompanyModal"
                            class="h-9 rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Edit Company
                        </button>
                        <div class="relative">
                            <button
                                type="button"
                                id="toggleCompanyHeaderMenu"
                                class="h-9 w-9 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50"
                            >
                                <i class="fas fa-ellipsis-v text-sm"></i>
                            </button>

                            <div id="companyHeaderMenu" class="hidden absolute right-0 top-11 z-30 min-w-[220px] rounded-md border border-gray-200 bg-white py-1 shadow-sm">
                                @if ($authUser && app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($authUser))
                                    <a href="{{ route('activities.official-business.travel-credits.show', $company->id) }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        Travel Credits
                                    </a>
                                @endif

                                <a href="{{ route('company.history', $company->id) }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    View Audit History
                                </a>
                                @if ($canDeleteRecords)
                                    <form method="POST" action="{{ route('company.destroy', $company->id) }}" onsubmit="return confirm('Delete this company? This will remove it from the company list.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                            Delete Company
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-slide-over id="editCompanyModal" width="sm:max-w-[640px] lg:max-w-[760px]">
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Edit Company</h2>
                <p class="mt-1 text-sm text-gray-500">Update the company profile shown across all Company pages.</p>
            </div>
            <button type="button" data-close-edit-company-modal class="h-9 w-9 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
    </div>

    <form id="editCompanyForm" method="POST" action="{{ route('company.update', $company->id) }}" class="flex min-h-0 flex-1 flex-col">
        @csrf
        @method('PUT')

        <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="edit_company_name" class="mb-1 block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
                        <input id="edit_company_name" name="company_name" type="text" value="{{ old('company_name', $company->company_name) }}" class="h-9 w-full rounded border border-gray-200 px-4 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required>
                        @error('company_name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="edit_company_type" class="mb-1 block text-sm font-medium text-gray-700">Company Type</label>
                        <input id="edit_company_type" name="company_type" type="text" value="{{ old('company_type', $company->company_type ?? '') }}" class="h-9 w-full rounded border border-gray-200 px-4 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @error('company_type')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="edit_company_email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input id="edit_company_email" name="email" type="email" value="{{ old('email', $company->email ?? '') }}" class="h-9 w-full rounded border border-gray-200 px-4 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="edit_company_phone" class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                        <input id="edit_company_phone" name="phone" type="text" value="{{ old('phone', $company->phone ?? '') }}" class="h-9 w-full rounded border border-gray-200 px-4 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @error('phone')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="edit_company_website" class="mb-1 block text-sm font-medium text-gray-700">Website</label>
                        <input id="edit_company_website" name="website" type="text" value="{{ old('website', $company->website ?? '') }}" class="h-9 w-full rounded border border-gray-200 px-4 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        @error('website')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="edit_company_address" class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                        <textarea id="edit_company_address" name="address" rows="3" class="w-full rounded border border-gray-200 px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">{{ old('address', $company->address ?? '') }}</textarea>
                        @error('address')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="edit_company_description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="edit_company_description" name="description" rows="3" class="w-full rounded border border-gray-200 px-4 py-3 text-sm text-gray-700 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100">{{ old('description', $company->description ?? '') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
            </div>
        </div>

        <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-close-edit-company-modal class="h-9 min-w-[100px] rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="h-9 min-w-[100px] rounded-full bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                    Save
                </button>
            </div>
        </div>
    </form>
</x-slide-over>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editCompanyModal = document.getElementById('editCompanyModal');
        const openEditCompanyModal = document.getElementById('openEditCompanyModal');
        const closeEditCompanyButtons = document.querySelectorAll('[data-close-edit-company-modal]');
        const menuButton = document.getElementById('toggleCompanyHeaderMenu');
        const menu = document.getElementById('companyHeaderMenu');
        const shouldOpenEditModal = @json($errors->has('company_name') || $errors->has('company_type') || $errors->has('email') || $errors->has('phone') || $errors->has('website') || $errors->has('address') || $errors->has('description'));

        const openModal = () => window.jkncSlideOver.open(editCompanyModal);
        const closeModal = () => window.jkncSlideOver.close(editCompanyModal);

        const closeMenu = () => {
            menu.classList.add('hidden');
        };

        openEditCompanyModal?.addEventListener('click', openModal);

        closeEditCompanyButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        editCompanyModal?.addEventListener('click', function (event) {
            if (event.target === editCompanyModal || event.target.hasAttribute('data-drawer-overlay')) {
                closeModal();
            }
        });

        menuButton?.addEventListener('click', function (event) {
            event.stopPropagation();
            menu.classList.toggle('hidden');
        });

        menu?.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
                closeMenu();
            }
        });

        if (shouldOpenEditModal) {
            openModal();
        }
    });
</script>
