@extends('layouts.app')
@section('title', 'Corporate Dashboard')

@section('content')
<div class="w-full h-full px-6 py-5"
     x-data="{
        search: '',
        moduleFilter: '',
        statusFilter: '',
        previewOpen: false,
        previewLoading: false,
        previewError: '',
        previewItem: null,
        matches(item) {
            const q = this.search.toLowerCase().trim();

            const matchesSearch =
                q === '' ||
                (item.title ?? '').toLowerCase().includes(q) ||
                (item.module ?? '').toLowerCase().includes(q) ||
                (item.company_reg_no ?? '').toLowerCase().includes(q) ||
                (item.uploaded_by ?? '').toLowerCase().includes(q) ||
                (item.date_uploaded ?? '').toLowerCase().includes(q);

            const matchesModule =
                this.moduleFilter === '' || item.module === this.moduleFilter;

            const matchesStatus =
                this.statusFilter === '' || item.status === this.statusFilter;

            return matchesSearch && matchesModule && matchesStatus;
        },
        clearFilters() {
            this.search = '';
            this.moduleFilter = '';
            this.statusFilter = '';
        },
        previewFields() {
            if (!this.previewItem) return [];

            const hidden = ['id', 'can_edit', 'can_submit', 'draft_documents', 'approved_documents', 'document_url', 'document_name', 'workflow_status', 'approval_status', 'review_note'];
            return Object.entries(this.previewItem)
                .filter(([key, value]) => !hidden.includes(key) && value !== null && value !== '')
                .map(([key, value]) => ({
                    label: key.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()),
                    value
                }));
        },
        documentKind(url) {
            const lower = (url || '').toLowerCase();
            if (lower.endsWith('.pdf')) return 'pdf';
            if (lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png')) return 'image';
            return '';
        },
        async openPreview(url) {
            if (!url) return;

            this.previewOpen = true;
            this.previewLoading = true;
            this.previewError = '';
            this.previewItem = null;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Unable to load record preview.');
                }

                this.previewItem = data;
            } catch (error) {
                this.previewError = error.message || 'Unable to load record preview.';
            } finally {
                this.previewLoading = false;
            }
        },
        closePreview() {
            this.previewOpen = false;
            this.previewItem = null;
            this.previewError = '';
        }
     }">

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl min-h-[calc(100vh-7rem)] flex flex-col">

        <div class="px-5 py-4 border-b border-gray-200">
            <h1 class="text-[30px] font-semibold text-gray-800 leading-none">Corporate Approval Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Review Corporate submissions for approval</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 px-5 pt-5">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-blue-600 uppercase">Submitted</p>
                <h2 class="text-3xl font-bold text-blue-700 mt-2">{{ $pendingCount }}</h2>
            </div>

            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-green-600 uppercase">Accepted</p>
                <h2 class="text-3xl font-bold text-green-700 mt-2">{{ $approvedCount }}</h2>
            </div>

            <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-red-600 uppercase">Rejected</p>
                <h2 class="text-3xl font-bold text-red-700 mt-2">{{ $rejectedCount }}</h2>
            </div>

            <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4">
                <p class="text-xs font-semibold text-yellow-600 uppercase">Reverted</p>
                <h2 class="text-3xl font-bold text-yellow-700 mt-2">{{ $revisionCount }}</h2>
            </div>
        </div>

        <div class="px-5 pt-5">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">
                            Search
                        </label>
                        <div class="relative w-full">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Search"
                                class="w-full bg-white border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 rounded-full pl-11 pr-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">
                            Module
                        </label>
                        <select
                            x-model="moduleFilter"
                            class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                            <option value="">All Modules</option>
                            <option value="SEC-COI">SEC-COI</option>
                            <option value="SEC-AOI">SEC-AOI</option>
                            <option value="Bylaws">Bylaws</option>
                            <option value="GIS">GIS</option>
                            <option value="LGU">LGU</option>
                            <option value="Accounting">Accounting</option>
                            <option value="Banking">Banking</option>
                            <option value="Operations">Operations</option>
                            <option value="Correspondence">Correspondence</option>
                            <option value="Legal">Legal</option>
                            <option value="Notices">Notices</option>
                            <option value="Minutes">Minutes</option>
                            <option value="Resolutions">Resolutions</option>
                            <option value="Secretary Certificates">Secretary Certificates</option>
                            <option value="BIR & Tax">BIR & Tax</option>
                            <option value="NatGov">NatGov</option>
                            <option value="Stock Transfer Book - Index">STB Index</option>
                            <option value="Stock Transfer Book - Journal">STB Journal</option>
                            <option value="Stock Transfer Book - Installment">STB Installment</option>
                            <option value="Stock Transfer Book - Certificate">STB Certificate</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">
                            Workflow Status
                        </label>
                        <select
                            x-model="statusFilter"
                            class="w-full h-11 rounded-lg border border-gray-300 px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                            <option value="">All Status</option>
                            <option value="Submitted">Submitted</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Reverted">Reverted</option>
                            <option value="Archived">Archived</option>
                            <option value="Uploaded">Uploaded</option>
                        </select>
                    </div>

                </div>

                <div class="mt-3 flex justify-end">
                    <button
                        type="button"
                        @click="clearFilters()"
                        class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-white transition">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="px-5 py-5 flex-1 flex flex-col">
            <div class="border border-gray-200 rounded-xl overflow-hidden flex-1">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Ref#</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Module</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Corporation / Record</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Company Reg No.</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Uploaded By</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Date Uploaded</th>
                            <th class="px-4 py-3 border-r border-gray-200 font-semibold">Workflow Status</th>
                            <th class="px-4 py-3 font-semibold text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white text-gray-700">
                        @forelse($items as $item)
                            @php
                                $statusClasses = match($item->status) {
                                    'Accepted' => 'bg-green-50 text-green-700',
                                    'Archived' => 'bg-gray-100 text-gray-700',
                                    'Reverted' => 'bg-yellow-50 text-yellow-700',
                                    'Submitted' => 'bg-blue-50 text-blue-700',
                                    default => 'bg-orange-50 text-orange-700',
                                };
                            @endphp

                            <tr class="border-t border-gray-200 hover:bg-gray-50 cursor-pointer"
                                @if(!empty($item->preview_route ?? null))
                                    @click="if ($event.target.closest('a,button,form')) return; openPreview(@js($item->preview_route));"
                                @else
                                    onclick="if (event.target.closest('a,button,form')) return; window.location='{{ $item->show_route }}';"
                                @endif
                                x-show="matches({
                                    title: @js($item->title),
                                    module: @js($item->module),
                                    company_reg_no: @js($item->company_reg_no),
                                    uploaded_by: @js((string) $item->uploaded_by),
                                    date_uploaded: @js((string) $item->date_uploaded),
                                    status: @js($item->status)
                                })">
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->id }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->module }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    @if(!empty($item->preview_route ?? null))
                                        <button type="button" @click.stop="openPreview(@js($item->preview_route))" class="text-left text-blue-700 hover:text-blue-900 hover:underline">
                                            {{ $item->title }}
                                        </button>
                                    @else
                                        <a href="{{ $item->show_route }}" class="text-blue-700 hover:text-blue-900 hover:underline">
                                            {{ $item->title }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->company_reg_no }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->uploaded_by }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">{{ $item->date_uploaded }}</td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $statusClasses }} font-medium">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        @if(($item->supports_actions ?? true) && $item->status === 'Submitted')
                                            <form action="{{ $item->approve_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition">
                                                    Approve
                                                </button>
                                            </form>

                                            <form action="{{ $item->reject_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                                                    Reject
                                                </button>
                                            </form>

                                            <form action="{{ $item->revise_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-yellow-600 text-white hover:bg-yellow-700 transition">
                                                    Revise
                                                </button>
                                            </form>
                                        @endif

                                        @if(($item->supports_actions ?? true) && $item->status === 'Accepted' && !empty($item->archive_route ?? null))
                                            <form action="{{ $item->archive_route }}" method="POST">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-700 text-white hover:bg-gray-800 transition">
                                                    Archive
                                                </button>
                                            </form>
                                        @endif

                                        @if(!($item->supports_actions ?? true))
                                            <span class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-600">
                                                Review in module
                                            </span>
                                        @endif

                                        @if(!empty($item->preview_route ?? null))
                                            <button type="button"
                                                    @click.stop="openPreview(@js($item->preview_route))"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                                                View
                                            </button>
                                        @else
                                            <a href="{{ $item->show_route }}"
                                               class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                                                View
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No corporate submissions found.
                                </td>
                            </tr>
                        @endforelse

                        <tr x-show="false"></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div x-show="previewOpen" x-cloak class="fixed inset-0 z-50 overflow-hidden">
        <div class="absolute inset-0 bg-black/40" @click="closePreview()"></div>

        <div class="absolute inset-y-0 right-0 flex w-[88vw] max-w-[1500px] min-w-[960px]">
            <div class="w-full bg-white shadow-2xl flex h-full">
                <div class="flex-1 min-w-0 p-4 bg-gray-50 border-r border-gray-200">
                    <div class="h-full bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div x-show="previewLoading" class="h-full flex items-center justify-center text-gray-500 text-sm">
                            Loading preview...
                        </div>

                        <div x-show="!previewLoading && previewError" class="h-full flex items-center justify-center text-red-600 text-sm px-6 text-center" x-text="previewError"></div>

                        <template x-if="!previewLoading && !previewError && previewItem && !previewItem.document_url">
                            <div class="h-full flex items-center justify-center text-gray-400 text-sm">
                                No document available for preview.
                            </div>
                        </template>

                        <template x-if="!previewLoading && !previewError && previewItem && documentKind(previewItem.document_url) === 'pdf'">
                            <iframe :src="previewItem.document_url" class="w-full h-full bg-white" frameborder="0"></iframe>
                        </template>

                        <template x-if="!previewLoading && !previewError && previewItem && documentKind(previewItem.document_url) === 'image'">
                            <div class="h-full flex items-center justify-center overflow-auto bg-white">
                                <img :src="previewItem.document_url" alt="Document Preview" class="max-w-full max-h-full object-contain">
                            </div>
                        </template>

                        <template x-if="!previewLoading && !previewError && previewItem && previewItem.document_url && !documentKind(previewItem.document_url)">
                            <div class="h-full flex items-center justify-center text-gray-500 text-sm px-6 text-center">
                                Preview is not available for this file type. Use Open Document.
                            </div>
                        </template>
                    </div>
                </div>

                <div class="w-[420px] shrink-0 flex flex-col bg-white">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">Submitted Record</h2>
                        <button type="button" @click="closePreview()" class="text-gray-400 hover:text-gray-600 text-lg">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <template x-if="previewItem">
                            <div class="space-y-5">
                                <div class="space-y-4 text-sm">
                                    <template x-for="field in previewFields()" :key="field.label">
                                        <div class="flex justify-between gap-4">
                                            <span class="text-gray-500" x-text="field.label"></span>
                                            <span class="text-right font-medium text-gray-900 break-words" x-text="field.value"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="pt-4 border-t border-gray-200 space-y-3">
                                    <div>
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Draft Documents</div>
                                        <template x-if="!(previewItem.draft_documents || []).length">
                                            <div class="mt-1 text-sm text-gray-400">None</div>
                                        </template>
                                        <template x-for="document in (previewItem.draft_documents || [])" :key="document.path || document.url">
                                            <a :href="document.url" target="_blank" class="mt-1 block text-sm text-blue-600 hover:underline break-all" x-text="document.name || 'Document'"></a>
                                        </template>
                                    </div>

                                    <div>
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Approved Documents</div>
                                        <template x-if="!(previewItem.approved_documents || []).length">
                                            <div class="mt-1 text-sm text-gray-400">None</div>
                                        </template>
                                        <template x-for="document in (previewItem.approved_documents || [])" :key="document.path || document.url">
                                            <a :href="document.url" target="_blank" class="mt-1 block text-sm text-emerald-700 hover:underline break-all" x-text="document.name || 'Document'"></a>
                                        </template>
                                    </div>
                                </div>

                                <a x-show="previewItem.document_url" :href="previewItem.document_url" target="_blank" class="block w-full rounded-lg border border-gray-300 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Open Document
                                </a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
