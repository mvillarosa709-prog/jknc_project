@php
    $fieldPrefix = $fieldPrefix ?? 'service';
    $modalId = $modalId ?? $fieldPrefix . 'Modal';
    $formId = $formId ?? $fieldPrefix . 'Form';
    $methodId = $methodId ?? $fieldPrefix . 'FormMethod';
    $titleId = $titleId ?? $fieldPrefix . 'ModalTitle';
    $submitId = $submitId ?? $fieldPrefix . 'FormSubmit';
@endphp

<x-slide-over :id="$modalId" width="sm:max-w-[680px] lg:max-w-[720px]">
    <div class="border-b border-gray-100 px-6 py-5 sm:px-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="{{ $titleId }}" class="text-2xl font-semibold text-gray-900">{{ $title }}</h2>
                @if (! empty($subtitle))
                    <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            <button type="button" data-close-service-modal class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
        </div>
    </div>

    <form
        id="{{ $formId }}"
        method="POST"
        action="{{ $action }}"
        class="flex min-h-0 flex-1 flex-col"
        x-data="serviceFormState({
            serviceAreaOptions: @js($serviceAreaOptions->values()),
            categoryOptions: @js($categories->values()),
            engagementOptions: @js($engagementOptions->values()),
            initialServiceArea: @js(array_values((array) old('service_area', []))),
            initialCategory: @js(old('category_other', old('category', ''))),
            initialEngagement: @js(array_values((array) old('engagement_structure', []))),
            initialFrequency: @js(old('frequency', '')),
            initialStatus: @js(old('status', 'Pending Approval')),
            initialAssignedUnit: @js(old('assigned_unit', '')),
            initialRequirementsIndividual: @js(old('requirements_individual', '')),
            initialRequirementsJuridical: @js(old('requirements_juridical', '')),
            initialRequirementsOther: @js(old('requirements_other', ''))
        })"
        x-init="initialize()"
    >
        @csrf

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <div class="font-semibold">Unable to save service.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <input type="hidden" id="{{ $methodId }}" name="_method" value="POST">

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6 sm:px-8">
            <div class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Service Intake</p>
                            <p class="mt-1 text-sm text-gray-500">Configure the catalog record before routing, pricing, scheduling, and recurring rules. After saving, it will be submitted for admin approval.</p>
                        </div>
                        <input id="{{ $fieldPrefix }}FormStatus" name="status" type="hidden" x-model="currentStatus" value="{{ old('status', 'Pending Approval') }}">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500">Submission Status</label>
                                <div class="flex h-10 items-center rounded-lg border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-700" x-text="currentStatus || 'Pending Approval'"></div>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500">Assigned Unit</label>
                                <select id="{{ $fieldPrefix }}FormAssignedUnit" name="assigned_unit" x-model="currentAssignedUnit" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select unit</option>
                                    @foreach ($assignedUnitOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($companyLocked ?? false)
                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                        <h3 class="text-base font-semibold text-gray-900">Company Link</h3>
                        <p class="mb-4 text-xs text-gray-500">This service will be linked to the current company record.</p>
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Linked Company</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $lockedCompany->company_name }}</p>
                        </div>
                    </section>
                @endif

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <h3 class="text-base font-semibold text-gray-900">Basic Information</h3>
                    <p class="mb-4 text-xs text-gray-500">Define the core name, summary, and delivery output of this service.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Service Name <span class="text-red-500">*</span></label>
                            <input id="{{ $fieldPrefix }}FormServiceName" name="service_name" type="text" value="{{ old('service_name') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                            @error('service_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Service Description <span class="text-red-500">*</span></label>
                                <textarea id="{{ $fieldPrefix }}FormServiceDescription" name="service_description" rows="4" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>{{ old('service_description') }}</textarea>
                                @error('service_description')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Service Activity / Output <span class="text-red-500">*</span></label>
                                <textarea id="{{ $fieldPrefix }}FormServiceOutput" name="service_activity_output" rows="4" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>{{ old('service_activity_output') }}</textarea>
                                @error('service_activity_output')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <h3 class="text-base font-semibold text-gray-900">Service Classification</h3>
                    <p class="mb-4 text-xs text-gray-500">Categorize the service area and service family before pricing and routing.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Service Area <span class="text-red-500">*</span></label>
                            <select id="{{ $fieldPrefix }}FormServiceArea" class="hidden" multiple x-model="selectedServiceAreas">
                                @foreach ($serviceAreaOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="grid gap-2 md:grid-cols-2">
                                    <template x-for="option in serviceAreaOptions" :key="option">
                                        <button
                                            type="button"
                                            @click="toggleServiceArea(option)"
                                            class="flex min-h-[50px] items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm font-medium transition"
                                            :class="selectedServiceAreas.includes(option) ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-200 hover:bg-blue-50/40'"
                                        >
                                            <span class="flex h-4 w-4 items-center justify-center rounded border text-[10px]" :class="selectedServiceAreas.includes(option) ? 'border-blue-500 bg-blue-600 text-white' : 'border-gray-300 bg-white text-transparent'">
                                                <i class="fas fa-check"></i>
                                            </span>
                                            <span x-text="option"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <template x-for="area in selectedServiceAreas" :key="'hidden-'+area">
                                <input type="hidden" name="service_area[]" :value="area">
                            </template>
                            <p class="mt-2 text-xs text-gray-500">Select one or more service areas.</p>
                            @error('service_area')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Other Service Area</label>
                            <input
                                id="{{ $fieldPrefix }}FormServiceAreaOther"
                                name="service_area_other"
                                type="text"
                                value="{{ old('service_area_other') }}"
                                :disabled="!showOtherServiceArea"
                                :class="showOtherServiceArea ? 'bg-white text-gray-900' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                                class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                placeholder="Specify other service area"
                            >
                            <p class="mt-2 text-xs text-gray-500">Use this only when `Others` is selected.</p>
                            @error('service_area_other')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Category</label>
                                <select id="{{ $fieldPrefix }}FormCategory" name="category" x-model="selectedCategory" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select category</option>
                                    <template x-for="option in categoryOptions" :key="option">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                                @error('category')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Add Category</label>
                                <div class="flex gap-2">
                                    <input
                                        id="{{ $fieldPrefix }}FormCategoryOther"
                                        x-model.trim="newCategory"
                                        name="category_other"
                                        type="text"
                                        value="{{ old('category_other') }}"
                                        class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        placeholder="Type new category"
                                        @keydown.enter.prevent="addCategory()"
                                    >
                                    <button
                                        type="button"
                                        @click="addCategory()"
                                        class="h-10 shrink-0 rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm font-medium text-blue-700 hover:bg-blue-100"
                                    >
                                        Add
                                    </button>
                                </div>
                                @error('category_other')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <h3 class="text-base font-semibold text-gray-900">Engagement & Scheduling</h3>
                    <p class="mb-4 text-xs text-gray-500">Define how the service is delivered and when it should be tracked.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Engagement Structure <span class="text-red-500">*</span></label>
                            <select id="{{ $fieldPrefix }}FormEngagement" class="hidden" multiple x-model="selectedEngagements">
                                @foreach ($engagementOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            <div class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="grid gap-2">
                                    <template x-for="option in engagementOptions" :key="option">
                                        <button
                                            type="button"
                                            @click="toggleEngagement(option)"
                                            class="flex min-h-[50px] items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm font-medium transition"
                                            :class="selectedEngagements.includes(option) ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-200 hover:bg-blue-50/40'"
                                        >
                                            <span class="flex h-4 w-4 items-center justify-center rounded border text-[10px]" :class="selectedEngagements.includes(option) ? 'border-blue-500 bg-blue-600 text-white' : 'border-gray-300 bg-white text-transparent'">
                                                <i class="fas fa-check"></i>
                                            </span>
                                            <span x-text="option"></span>
                                        </button>
                                    </template>
                                </div>
                                <template x-for="engagement in selectedEngagements" :key="'engagement-hidden-'+engagement">
                                    <input type="hidden" name="engagement_structure[]" :value="engagement">
                                </template>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Select one or more engagement types.</p>
                            @error('engagement_structure')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Frequency</label>
                                <select id="{{ $fieldPrefix }}FormFrequency" name="frequency" @change="syncFrequency($event.target.value)" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select frequency</option>
                                    @foreach ($frequencyOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('frequency')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Reminder Lead Time</label>
                                <select id="{{ $fieldPrefix }}FormReminder" name="reminder_lead_time" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select lead time</option>
                                    @foreach ($reminderLeadTimes as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="showScheduleRule" x-cloak class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700">Schedule Rule</label>
                                <input id="{{ $fieldPrefix }}FormScheduleRule" name="schedule_rule" type="text" value="{{ old('schedule_rule') }}" placeholder="Every 5th of the month" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('schedule_rule')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div x-show="showDeadline" x-cloak class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700">Deadline</label>
                                <input id="{{ $fieldPrefix }}FormDeadline" name="deadline" type="datetime-local" value="{{ old('deadline') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('deadline')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-3">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Status</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800" x-text="currentStatus || 'Draft'"></p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-3">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Assigned Unit</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800" x-text="currentAssignedUnit || 'Not set'"></p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-3">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Recurring</p>
                                <p class="mt-1 text-sm font-semibold" :class="isRecurring ? 'text-emerald-700' : 'text-gray-700'" x-text="isRecurring ? 'Yes' : 'No'"></p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <h3 class="text-base font-semibold text-gray-900">Requirements</h3>
                    <p class="mb-4 text-xs text-gray-500">Deals will fetch only the matching group based on the selected client or business organization type.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">SOLE / NATURAL PERSON / INDIVIDUAL</label>
                            <textarea id="{{ $fieldPrefix }}FormRequirementsIndividual" name="requirements_individual" x-model="requirementsIndividual" rows="4" placeholder="Enter one requirement per line" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{{ old('requirements_individual', '') }}</textarea>
                            <p class="mt-2 text-xs text-gray-500">Each line is saved as one bullet item.</p>
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-2" x-show="bulletItems(requirementsIndividual).length" x-cloak>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Bullet Preview</p>
                                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                    <template x-for="(item, index) in bulletItems(requirementsIndividual)" :key="'individual-' + index">
                                        <li class="flex items-start gap-2">
                                            <span class="mt-[2px] text-blue-600">&bull;</span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">JURIDICAL ENTITY (Corporation / OPC / Partnership / Cooperative)</label>
                            <textarea id="{{ $fieldPrefix }}FormRequirementsJuridical" name="requirements_juridical" x-model="requirementsJuridical" rows="4" placeholder="Enter one requirement per line" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{{ old('requirements_juridical', '') }}</textarea>
                            <p class="mt-2 text-xs text-gray-500">Each line is saved as one bullet item.</p>
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-2" x-show="bulletItems(requirementsJuridical).length" x-cloak>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Bullet Preview</p>
                                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                    <template x-for="(item, index) in bulletItems(requirementsJuridical)" :key="'juridical-' + index">
                                        <li class="flex items-start gap-2">
                                            <span class="mt-[2px] text-blue-600">&bull;</span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Other Requirements</label>
                            <textarea id="{{ $fieldPrefix }}FormRequirementsOther" name="requirements_other" x-model="requirementsOther" rows="4" placeholder="Enter one requirement per line" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{{ old('requirements_other', '') }}</textarea>
                            <p class="mt-2 text-xs text-gray-500">Each line is saved as one bullet item.</p>
                            <div class="mt-2 rounded-lg border border-dashed border-gray-200 bg-white px-3 py-2" x-show="bulletItems(requirementsOther).length" x-cloak>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Bullet Preview</p>
                                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                    <template x-for="(item, index) in bulletItems(requirementsOther)" :key="'other-' + index">
                                        <li class="flex items-start gap-2">
                                            <span class="mt-[2px] text-blue-600">&bull;</span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <input type="hidden" id="{{ $fieldPrefix }}FormRequirementCategory" name="requirement_category" value="{{ old('requirement_category') }}">
                        <input type="hidden" id="{{ $fieldPrefix }}FormRequirements" name="requirements" value="{{ old('requirements') }}">
                        @error('requirement_category')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                    <h3 class="text-base font-semibold text-gray-900">Pricing</h3>
                    <p class="mb-4 text-xs text-gray-500">Capture unit economics, caps, and fallback fees for service billing.</p>
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Unit <span class="text-red-500">*</span></label>
                                <select id="{{ $fieldPrefix }}FormUnit" name="unit" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
                                    <option value="">Select unit</option>
                                    @foreach ($unitOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('unit')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Rate per Unit</label>
                                <input id="{{ $fieldPrefix }}FormRatePerUnit" name="rate_per_unit" type="number" min="0" step="0.01" value="{{ old('rate_per_unit') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('rate_per_unit')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Minimum Units</label>
                                <input id="{{ $fieldPrefix }}FormMinUnits" name="min_units" type="number" min="1" step="1" value="{{ old('min_units') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Maximum Cap</label>
                                <input id="{{ $fieldPrefix }}FormMaxCap" name="max_cap" type="number" min="0" step="0.01" value="{{ old('max_cap') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Price / Fee</label>
                                <input id="{{ $fieldPrefix }}FormPriceFee" name="price_fee" type="number" min="0" step="0.01" value="{{ old('price_fee') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                @error('price_fee')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Cost of Service</label>
                                <input id="{{ $fieldPrefix }}FormCost" name="cost_of_service" type="number" min="0" step="0.01" value="{{ old('cost_of_service') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Tax Treatment <span class="text-red-500">*</span></label>
                            <div class="space-y-2">
                                @foreach ($taxTypeOptions as $option)
                                    <label class="flex items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700">
                                        <input
                                            id="{{ $fieldPrefix }}FormTaxType{{ \Illuminate\Support\Str::slug($option) }}"
                                            name="tax_type"
                                            type="radio"
                                            value="{{ $option }}"
                                            class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                            @checked(old('tax_type', 'Tax Exclusive') === $option)
                                        >
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Tax Inclusive means the entered amount already includes tax. Tax Exclusive means tax is added on top later.</p>
                            @error('tax_type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                @if ($customFields->isNotEmpty())
                    <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                        <h3 class="text-base font-semibold text-gray-900">Custom Fields</h3>
                        <p class="mb-4 text-xs text-gray-500">Capture any additional metadata configured for service records.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($customFields as $field)
                                @php
                                    $fieldName = 'custom_fields['.$field->field_key.']';
                                    $fieldId = $fieldPrefix.'CustomField'.\Illuminate\Support\Str::studly($field->field_key);
                                @endphp
                                <div class="{{ $field->field_type === 'textarea' ? 'md:col-span-2' : '' }}">
                                    <label class="mb-2 block text-sm font-medium text-gray-700">
                                        {{ $field->field_name }}
                                        @if ($field->is_required)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>

                                    @if ($field->field_type === 'textarea')
                                        <textarea
                                            id="{{ $fieldId }}"
                                            name="{{ $fieldName }}"
                                            rows="3"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >{{ old($fieldName) }}</textarea>
                                    @elseif ($field->field_type === 'picklist')
                                        <select
                                            id="{{ $fieldId }}"
                                            name="{{ $fieldName }}"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">Select {{ strtolower($field->field_name) }}</option>
                                            @foreach ($field->options ?? [] as $option)
                                                <option value="{{ $option }}" @selected(old($fieldName) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->field_type === 'checkbox')
                                        <label class="flex h-10 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700">
                                            <input
                                                id="{{ $fieldId }}"
                                                name="{{ $fieldName }}"
                                                type="checkbox"
                                                value="1"
                                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                @checked(old($fieldName))
                                            >
                                            <span>Enabled</span>
                                        </label>
                                    @else
                                        <input
                                            id="{{ $fieldId }}"
                                            name="{{ $fieldName }}"
                                            type="{{ in_array($field->field_type, ['number', 'currency'], true) ? 'number' : 'text' }}"
                                            value="{{ old($fieldName) }}"
                                            @if (in_array($field->field_type, ['number', 'currency'], true))
                                                step="0.01"
                                            @endif
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                    @endif

                                    @error($fieldName)
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <input
                    id="{{ $fieldPrefix }}FormCompany"
                    name="company_id"
                    type="hidden"
                    value="{{ old('company_id', $companyLocked ? $lockedCompany->id : '') }}"
                >
            </div>
        </div>

        <div class="border-t border-gray-100 px-6 py-4 sm:px-8">
            <div class="flex items-center justify-end gap-3">
                <button type="button" data-close-service-modal class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button id="{{ $submitId }}" type="submit" class="h-10 rounded-lg bg-blue-600 px-5 text-sm font-medium text-white hover:bg-blue-700">
                    Save
                </button>
            </div>
        </div>
    </form>
</x-slide-over>

<script>
function serviceFormState(config) {
    return {
        serviceAreaOptions: config.serviceAreaOptions ?? [],
        categoryOptions: config.categoryOptions ?? [],
        engagementOptions: config.engagementOptions ?? [],
        selectedServiceAreas: config.initialServiceArea ?? [],
        selectedCategory: config.initialCategory ?? '',
        newCategory: '',
        selectedEngagements: config.initialEngagement ?? [],
        frequency: config.initialFrequency ?? '',
        showDeadline: false,
        showScheduleRule: false,
        showOtherServiceArea: false,
        isRecurring: false,
        currentStatus: config.initialStatus ?? 'Pending Approval',
        currentAssignedUnit: config.initialAssignedUnit ?? '',
        requirementsIndividual: config.initialRequirementsIndividual ?? '',
        requirementsJuridical: config.initialRequirementsJuridical ?? '',
        requirementsOther: config.initialRequirementsOther ?? '',

        initialize() {
            this.selectedServiceAreas = Array.isArray(this.selectedServiceAreas) ? this.selectedServiceAreas : [];
            this.selectedEngagements = Array.isArray(this.selectedEngagements) ? this.selectedEngagements : [];
            this.selectedCategory = this.selectedCategory ?? '';
            this.syncServiceAreaState();
            this.syncFrequency(this.frequency);
            this.syncRecurring();

            const serviceAreaSelect = document.getElementById('{{ $fieldPrefix }}FormServiceArea');
            serviceAreaSelect?.addEventListener('change', (event) => {
                this.selectedServiceAreas = Array.from(event.target.selectedOptions).map((option) => option.value);
                this.syncServiceAreaState();
            });

            const engagementSelect = document.getElementById('{{ $fieldPrefix }}FormEngagement');
            engagementSelect?.addEventListener('change', (event) => {
                this.selectedEngagements = Array.from(event.target.selectedOptions).map((option) => option.value);
                this.syncRecurring();
            });

            this.$watch('selectedServiceAreas', () => this.syncServiceAreaState());
            this.$watch('selectedEngagements', () => this.syncRecurring());
        },

        toggleServiceArea(option) {
            if (this.selectedServiceAreas.includes(option)) {
                this.selectedServiceAreas = this.selectedServiceAreas.filter((value) => value !== option);
            } else {
                this.selectedServiceAreas = [...this.selectedServiceAreas, option];
            }
            this.syncHiddenSelect('{{ $fieldPrefix }}FormServiceArea', this.selectedServiceAreas);
            this.syncServiceAreaState();
        },

        toggleEngagement(option) {
            if (this.selectedEngagements.includes(option)) {
                this.selectedEngagements = this.selectedEngagements.filter((value) => value !== option);
            } else {
                this.selectedEngagements = [...this.selectedEngagements, option];
            }
            this.syncHiddenSelect('{{ $fieldPrefix }}FormEngagement', this.selectedEngagements);
            this.syncRecurring();
        },

        syncHiddenSelect(id, values) {
            const select = document.getElementById(id);
            if (!select) return;
            Array.from(select.options).forEach((option) => {
                option.selected = values.includes(option.value);
            });
        },

        syncServiceAreaState() {
            this.showOtherServiceArea = this.selectedServiceAreas.includes('Others');
            if (!this.showOtherServiceArea) {
                const input = document.getElementById('{{ $fieldPrefix }}FormServiceAreaOther');
                if (input) input.value = '';
            }
        },

        addCategory() {
            const value = (this.newCategory || '').trim();
            if (!value) return;
            if (!this.categoryOptions.includes(value)) {
                this.categoryOptions = [...this.categoryOptions, value].sort((a, b) => a.localeCompare(b));
            }
            this.selectedCategory = value;
            this.newCategory = '';
        },

        syncFrequency(value) {
            this.frequency = value || '';
            this.showDeadline = this.frequency !== '';
            this.showScheduleRule = ['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Annually', 'Custom'].includes(this.frequency);
        },

        syncRecurring() {
            this.isRecurring = this.selectedEngagements.includes('Regular (Retainer)') || this.selectedEngagements.includes('Hybrid');
        },

        bulletItems(value) {
            return String(value || '')
                .split(/\r?\n/)
                .map((item) => item.trim())
                .filter((item) => item !== '');
        },
    };
}
</script>
