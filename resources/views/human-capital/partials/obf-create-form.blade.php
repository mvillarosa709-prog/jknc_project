<section class="form-section obv3-section" x-show="!isEdit || Number(form.workflow_version || 3) >= 3" x-cloak>
    <h3 class="form-title">O.B. Classification</h3>
    <p class="text-sm text-gray-600 mb-3">What is this Official Business for?</p>
    <div class="grid grid-cols-3 gap-2 obv3-classification">
        <template x-for="option in classifications" :key="option">
            <div class="relative">
                <button type="button"
                    @click="changeClassification(option)"
                    :disabled="isView"
                    :aria-pressed="form.classification === option"
                    :class="form.classification === option
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50'"
                    class="obv3-classification-choice w-full rounded-lg border pl-3 pr-8 py-2 text-center text-xs font-semibold leading-tight transition disabled:opacity-60">
                    <span x-text="classificationLabel(option)"></span>
                </button>

                <button type="button"
                    class="obv3-classification-info"
                    :aria-label="`About ${classificationLabel(option)}`"
                    @click.stop
                    @mouseenter="$el.nextElementSibling.classList.add('show')"
                    @mouseleave="$el.nextElementSibling.classList.remove('show')"
                    @focus="$el.nextElementSibling.classList.add('show')"
                    @blur="$el.nextElementSibling.classList.remove('show')">
                    i
                </button>

                <div class="obv3-classification-tooltip"
                    :class="option === 'client'
                        ? 'obv3-tooltip-left'
                        : option === 'internal'
                            ? 'obv3-tooltip-center'
                            : 'obv3-tooltip-right'"
                    x-text="classificationDescription(option)">
                </div>
            </div>
        </template>
    </div>
</section>

<div x-show="form.classification" x-cloak>
    <section class="form-section obv3-section">
        <div class="flex items-center justify-between gap-2"><h3 class="form-title">Personnel Details</h3><button type="button" x-show="!isView" @click="openTeamMemberModal()" class="px-3 py-2 rounded-lg border border-blue-600 text-blue-700 text-xs font-semibold">+ Add Team Member</button></div>
        <div class="overflow-x-auto"><table class="w-full text-xs border-collapse obv3-data-table">
            <thead class="bg-blue-50 text-blue-900"><tr><th class="p-2 text-left">Employee</th><th class="p-2 text-left">Employee ID</th><th class="p-2 text-left">Position</th><th class="p-2 text-left">Department / Unit</th><th class="p-2 text-left">Nature of Travel</th><th class="p-2 text-left">Immediate Supervisor</th><th class="p-2 text-left">Action</th></tr></thead>
            <tbody>
                <tr class="border-b">
                    <td class="p-2"><span x-text="preview.full_name || form.employee_name || '-' "></span><span class="block text-blue-700 font-semibold">Primary Traveler</span><template x-if="canManageObf && !isView"><select x-model="form.employee_id" @change="selectEmployee(); removePrimaryFromTeam()" class="input mt-1"><template x-for="employee in employees" :key="employee.id"><option :value="employee.id" x-text="employee.full_name"></option></template></select></template></td>
                    <td class="p-2" x-text="preview.employee_code || form.employee_code || '-' "></td><td class="p-2" x-text="preview.position || '-' "></td><td class="p-2" x-text="preview.department || '-' "></td>
                    <td class="p-2"><select x-model="form.nature_of_travel" :disabled="isView" class="input"><option value="">Select</option><template x-for="nature in personnelNatureOptions" :key="nature"><option :value="nature" x-text="nature"></option></template></select></td>
                    <td class="p-2" x-text="form.immediate_superior || '-' "></td><td class="p-2 text-gray-400">&mdash;</td>
                </tr>
                <template x-for="(member, index) in form.team_rows || []" :key="member.employee_id"><tr class="border-b">
                    <td class="p-2"><span x-text="member.full_name"></span><span class="block text-gray-500">Team Member</span></td><td class="p-2" x-text="member.employee_code || '-' "></td><td class="p-2" x-text="member.position || '-' "></td><td class="p-2" x-text="member.department || '-' "></td>
                    <td class="p-2" x-text="member.nature_of_travel || '-' "></td><td class="p-2" x-text="member.immediate_superior || '-' "></td>
                    <td class="p-2 whitespace-nowrap"><button type="button" x-show="!isView" @click="openTeamMemberModal(index)" class="text-blue-700 mr-2">Edit</button><button type="button" x-show="!isView" @click="removeTeamMember(index)" class="text-red-600">Remove</button></td>
                </tr></template>
            </tbody>
        </table></div>
    </section>

    <section class="form-section obv3-section">
        <div class="flex items-center justify-between gap-2"><h3 class="form-title" x-text="detailSectionTitle()"></h3><button type="button" x-show="!isView" @click="openDetailModal()" class="px-3 py-2 rounded-lg border border-blue-600 text-blue-700 text-xs font-semibold" x-text="detailAddLabel()"></button></div>
        <div class="overflow-x-auto"><table class="w-full text-xs obv3-data-table"><thead class="bg-blue-50 text-blue-900"><tr>
            <th class="p-2 text-left" x-text="form.classification === 'client' ? 'Client' : form.classification === 'social' ? 'Activity / Event' : 'From'"></th>
            <th class="p-2 text-left" x-show="form.classification !== 'internal'">From</th><th class="p-2 text-left">To / Destination</th><th class="p-2 text-left">Purpose</th>
            <th class="p-2 text-left" x-text="form.classification === 'client' ? 'Type' : 'Activity Type'"></th>
            <th class="p-2 text-left" x-text="form.classification === 'client' ? 'Related Engagement' : form.classification === 'internal' ? 'Related Department / Office' : 'Organization / Host'"></th>
            <th class="p-2 text-left" x-show="form.classification === 'client'">Travel Credit Balance</th><th class="p-2 text-left" x-show="form.classification === 'internal'">Related Internal Record</th><th class="p-2 text-left" x-show="form.classification === 'social'">Related Contact</th><th class="p-2 text-left">Action</th>
        </tr></thead><tbody>
            <template x-for="(row, index) in form.detail_rows || []" :key="row.id || row.client_key || index"><tr class="border-b">
                <td class="p-2" x-text="detailHeading(row)"></td><td class="p-2" x-show="form.classification !== 'internal'" x-text="row.from_location || '-' "></td><td class="p-2" x-text="row.destination || '-' "></td><td class="p-2" x-text="row.purpose || '-' "></td>
                <td class="p-2" x-text="row.engagement_type || row.internal_activity_type || row.social_activity_type || '-' "></td><td class="p-2" x-text="detailRelated(row)"></td>
                <td class="p-2" x-show="form.classification === 'client'" x-text="row.travel_credit_balance ?? '-'"></td><td class="p-2" x-show="form.classification === 'internal'" x-text="row.related_internal_type || (row.related_internal_id ? `Record #${row.related_internal_id}` : '-')"></td><td class="p-2" x-show="form.classification === 'social'" x-text="contactName(row.contact_id) || row.related_contact_text || '-' "></td>
                <td class="p-2 whitespace-nowrap"><button type="button" x-show="!isView" @click="openDetailModal(index)" class="text-blue-700 mr-2">Edit</button><button type="button" x-show="!isView" @click="removeDetail(index)" class="text-red-600">Remove</button></td>
            </tr></template>
            <tr x-show="!(form.detail_rows || []).length"><td colspan="8" class="p-3 text-center text-gray-500">No O.B. Details yet.</td></tr>
        </tbody></table></div>
    </section>

    <section class="form-section obv3-section"><h3 class="form-title">Travel Details</h3>
        <div class="overflow-x-auto"><table class="w-full text-xs obv3-data-table"><thead class="bg-blue-50 text-blue-900"><tr><th class="p-2 text-left">Client / Trip</th><th class="p-2 text-left">Route</th><th class="p-2 text-left">Departure Date</th><th class="p-2 text-left">Departure Time</th><th class="p-2 text-left">Expected Arrival Date</th><th class="p-2 text-left">Expected Arrival Time</th><th class="p-2 text-left">Estimated Expense</th><th class="p-2 text-left">Action</th></tr></thead><tbody>
            <template x-for="(row, index) in form.detail_rows || []" :key="`travel-${row.id || row.client_key || index}`"><tr class="border-b"><td class="p-2" x-text="detailTripLabel(row)"></td><td class="p-2" x-text="detailRoute(row)"></td><td class="p-2" x-text="row.travel?.departure_date || '-'"></td><td class="p-2" x-text="row.travel?.departure_time || '-'"></td><td class="p-2" x-text="row.travel?.expected_arrival_date || '-'"></td><td class="p-2" x-text="row.travel?.expected_arrival_time || '-'"></td><td class="p-2" x-text="formatExpense(row.travel?.estimated_expense)"></td><td class="p-2"><button type="button" x-show="!isView" @click="openTravelModal(index)" class="text-blue-700" x-text="travelActionLabel(row)"></button></td></tr></template>
            <tr x-show="!(form.detail_rows || []).length"><td colspan="8" class="p-3 text-center text-gray-500">Add an O.B. Detail first.</td></tr>
        </tbody></table></div>
    </section>

    <section class="form-section obv3-section"><h3 class="form-title">Mode of Transportation</h3>
        <div class="overflow-x-auto"><table class="w-full text-xs obv3-data-table"><thead class="bg-blue-50 text-blue-900"><tr><th class="p-2 text-left">Client / Trip</th><th class="p-2 text-left">Route</th><th class="p-2 text-left">Mode</th><th class="p-2 text-left">Transportation Details</th><th class="p-2 text-left">Action</th></tr></thead><tbody>
            <template x-for="(row, index) in form.detail_rows || []" :key="`transport-${row.id || row.client_key || index}`"><tr class="border-b"><td class="p-2" x-text="detailTripLabel(row)"></td><td class="p-2" x-text="detailRoute(row)"></td><td class="p-2" x-text="row.transportation?.mode ? transportationModeLabel(row.transportation.mode) : 'Not selected'"></td><td class="p-2" x-text="transportationDescription(row.transportation || {})"></td><td class="p-2"><button type="button" x-show="!isView" @click="openTransportationModal(index)" class="text-blue-700" x-text="row.transportation?.mode ? 'Edit' : 'Select'"></button></td></tr></template>
            <tr x-show="!(form.detail_rows || []).length"><td colspan="5" class="p-3 text-center text-gray-500">Add an O.B. Detail first.</td></tr>
        </tbody></table></div>
    </section>
</div>

<section class="form-section obv3-section" x-show="form.classification" x-cloak>
    <div class="flex items-center justify-between gap-3"><h3 class="form-title">Attachments</h3><button type="button" x-show="!isView" @click="addCreateAttachment()" class="rounded-lg border border-blue-600 px-3 py-2 text-xs font-semibold text-blue-700">+ Add Attachment</button></div>
    <div class="overflow-x-auto mt-3"><table class="w-full text-xs obv3-data-table"><thead class="bg-blue-50 text-blue-900"><tr><th class="p-2 text-left">Attachment Type</th><th class="p-2 text-left">Related Client / Trip</th><th class="p-2 text-left">File</th><th class="p-2 text-left">Description</th><th class="p-2 text-left">Action</th></tr></thead><tbody>
        <template x-for="(attachment, index) in form.create_attachments || []" :key="attachment.id || attachment.key"><tr class="border-b">
            <td class="p-2"><span x-show="!attachment.remove" x-text="attachment.type || '-' "></span><span x-show="attachment.remove">Remove <span x-text="attachment.filename || 'this attachment'"></span>?</span></td>
            <td class="p-2" x-show="!attachment.remove" x-text="attachment.detail_key ? attachmentTripLabelForKey(attachment.detail_key) : 'General O.B.'"></td>
            <td class="p-2" x-show="!attachment.remove"><a x-show="attachment.id && attachment.url" :href="attachment.url" target="_blank" rel="noopener" class="text-blue-700" x-text="attachment.filename || 'Open file'"></a><span x-show="!attachment.id || !attachment.url" x-text="attachment.filename || 'File pending'"></span></td>
            <td class="p-2" x-show="!attachment.remove" x-text="attachment.description || '-' "></td>
            <td class="p-2 whitespace-nowrap"><input type="file" :id="`ob-attachment-file-${attachment.key}`" :name="`attachment_files[${index}]`" :disabled="isView || attachment.remove" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.mp3,.wav,.m4a,.mp4,.mov" @change="setCreateAttachmentFile(index, $event)"><button type="button" x-show="!isView && !attachment.remove" @click="openCreateAttachment(index)" class="text-blue-700 mr-2">Edit</button><button type="button" x-show="!isView && !attachment.remove" @click="removeCreateAttachment(index)" class="text-red-600">Remove</button><button type="button" x-show="!isView && attachment.remove" @click="attachment.remove = false; dirty = true" class="text-blue-700">Undo</button></td>
        </tr></template>
        <template x-for="(legacyFile, legacyIndex) in form.attachment_paths || []" :key="legacyFile.path || legacyIndex"><tr class="border-b"><td class="p-2" x-text="legacyFile.type || form.attachment_types?.[legacyIndex] || 'Existing O.B. attachment'"></td><td class="p-2">General O.B.</td><td class="p-2"><a :href="legacyFile.url" target="_blank" rel="noopener" x-text="legacyFile.original_name || 'Previously uploaded file'"></a></td><td class="p-2">&mdash;</td><td class="p-2">&mdash;</td></tr></template>
        <tr x-show="!(form.create_attachments || []).length && !(form.attachment_paths || []).length"><td colspan="5" class="p-3 text-center text-gray-500">No attachments added.</td></tr>
    </tbody></table></div>
</section>

<section class="form-section obv3-section" x-show="form.classification" x-cloak>
    <h3 class="form-title">Remarks</h3>
    <textarea name="remarks" x-model="form.remarks" :readonly="isView" rows="4" class="input" placeholder="Enter remarks..."></textarea>
</section>

<div x-show="teamModalOpen" x-cloak class="obv3-modal-backdrop" @click.self="teamModalOpen = false; teamModalIndex = null"><div class="obv3-modal max-w-2xl"><div class="obv3-modal-head"><h3 class="form-title" x-text="teamModalIndex === null ? 'Add Team Member' : 'Edit Team Member'">Add Team Member</h3><button type="button" @click="teamModalOpen = false; teamModalIndex = null" class="text-2xl text-gray-500">&times;</button></div><div class="obv3-modal-body space-y-3">
    <label class="label">Employee *</label><select x-model="teamDraft.employee_id" class="input"><option value="">Select active employee</option><template x-for="employee in personnelEmployees" :key="employee.id"><option :value="employee.id" :disabled="String(employee.id) === String(form.employee_id) || (form.team_rows || []).some(row => String(row.employee_id) === String(employee.id))" x-text="employee.full_name"></option></template></select>
    <div class="grid grid-cols-2 gap-2 text-xs"><div>Employee ID: <span x-text="selectedTeamEmployee()?.employee_code || '-' "></span></div><div>Position: <span x-text="selectedTeamEmployee()?.position || '-' "></span></div><div>Department / Unit: <span x-text="selectedTeamEmployee()?.department || '-' "></span></div><div>Immediate Supervisor: <span x-text="selectedTeamEmployee()?.immediate_superior || '-' "></span></div></div>
    <label class="label">Nature of Travel *</label><select x-model="teamDraft.nature_of_travel" class="input"><option value="">Select nature</option><template x-for="nature in personnelNatureOptions" :key="nature"><option :value="nature" x-text="nature"></option></template></select>
</div><div class="obv3-modal-foot"><button type="button" @click="teamModalOpen = false; teamModalIndex = null" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="addTeamMember()" class="px-3 py-2 bg-blue-600 text-white rounded-lg" x-text="teamModalIndex === null ? 'Add Team Member' : 'Save Changes'">Add Team Member</button></div></div></div>

<div x-show="detailModalOpen" x-cloak class="obv3-modal-backdrop" @click.self="detailModalOpen = false"><div class="obv3-modal max-w-2xl"><div class="obv3-modal-head"><h3 class="form-title" x-text="detailModalIndex === null ? detailAddLabel() : 'Edit O.B. Detail'"></h3><button type="button" @click="detailModalOpen = false" class="text-2xl text-gray-500">&times;</button></div><div class="obv3-modal-body space-y-3">
    <div x-show="form.classification === 'client'"><label class="label">Client *</label><select x-model="detailDraft.company_id" @change="changeDetailClient($event.target.value)" class="input"><option value="">Select client</option><template x-for="company in companies" :key="company.id"><option :value="company.id" x-text="company.company_name"></option></template></select></div>
    <div x-show="form.classification === 'social'"><label class="label">Activity / Event Name *</label><input x-model="detailDraft.activity_event_name" class="input"></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">From *</label><input x-model="detailDraft.from_location" list="ob-from-suggestions" @focus="loadSuggestions('from')" class="input"></div><div><label class="label">To / Destination *</label><input x-model="detailDraft.destination" list="ob-destination-suggestions" @focus="loadSuggestions('destination')" class="input"></div></div>
    <div><label class="label">Purpose *</label><input x-model="detailDraft.purpose" list="ob-purpose-suggestions" @focus="loadSuggestions('purpose')" class="input"></div><template x-for="field in ['from','destination','purpose']" :key="field"><datalist :id="`ob-${field}-suggestions`"><template x-for="value in suggestions[field] || []" :key="value"><option :value="value"></option></template></datalist></template>
    <div x-show="form.classification === 'client'" class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">Type *</label><select x-model="detailDraft.engagement_type" @change="changeEngagementType($event.target.value)" class="input"><option value="">Select type</option><option>Regular</option><option>Project</option></select></div><div x-show="detailDraft.engagement_type"><label class="label">Related Engagement *</label><select :value="clientEngagementValue()" @change="selectDetailEngagement($event.target.value, $event.target.selectedOptions[0]?.textContent)" class="input"><option value="" x-text="detailDraft.engagement_type === 'Regular' ? 'Select Regular engagement' : 'Select Project'"></option><template x-if="clientEngagementValue() && !engagements.some(item => String(item.id) === String(clientEngagementValue()))"><option :value="String(clientEngagementValue())" selected disabled x-text="(detailDraft.engagement_name || 'Saved engagement') + ' (unavailable)'"></option></template><template x-for="engagement in engagements" :key="engagement.id"><option :value="String(engagement.id)" :selected="String(engagement.id) === String(clientEngagementValue())" x-text="engagement.name"></option></template></select></div><div class="col-span-2"><label class="label">Travel Credit Balance</label><input :value="detailDraft.travel_credit_balance ?? '-'" readonly class="input bg-gray-100"></div></div>
    <div x-show="form.classification === 'internal'" class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">Internal Activity Type *</label><select x-model="detailDraft.internal_activity_type" class="input"><option value="">Select type</option><template x-for="type in internalActivityTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div><div><label class="label">Related Department / Office *</label><select x-model="detailDraft.department_id" class="input"><option value="">Select department</option><template x-for="department in departments" :key="department.id"><option :value="department.id" x-text="department.department_name"></option></template></select></div><div class="col-span-2"><label class="label">Related Internal Record</label><input x-model="detailDraft.related_internal_type" placeholder="Optional task, meeting, record, compliance reference..." class="input"></div></div>
    <div x-show="form.classification === 'social'" class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">Activity Type *</label><select x-model="detailDraft.social_activity_type" class="input"><option value="">Select type</option><template x-for="type in socialActivityTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div><div><label class="label">Organization / Host</label><input x-model="detailDraft.organization_host" class="input"></div><div class="col-span-2"><label class="label">Related Contact</label><select x-model="detailDraft.contact_id" class="input"><option value="">Select contact</option><template x-for="contact in contacts" :key="contact.id"><option :value="contact.id" x-text="contact.label"></option></template></select><input x-model="detailDraft.related_contact_text" placeholder="Or enter contact" class="input mt-2"></div></div>
</div><div class="obv3-modal-foot"><button type="button" @click="detailModalOpen = false" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="saveDetail()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Save Detail</button></div></div></div>

<div x-show="travelModalOpen" x-cloak class="obv3-modal-backdrop" @click.self="travelModalOpen = false"><div class="obv3-modal max-w-2xl"><div class="obv3-modal-head"><h3 class="form-title">Edit Travel Details</h3><button type="button" @click="travelModalOpen = false" class="text-2xl text-gray-500">&times;</button></div><div class="obv3-modal-body space-y-3">
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">Client / Trip</label><input :value="selectedTravelRow ? detailTripLabel(selectedTravelRow) : ''" readonly class="input bg-gray-100"></div><div><label class="label">Route</label><input :value="selectedTravelRow ? detailRoute(selectedTravelRow) : ''" readonly class="input bg-gray-100"></div><div><label class="label">Departure Date *</label><input type="date" x-model="travelDraft.departure_date" class="input"></div><div><label class="label">Departure Time *</label><input type="time" x-model="travelDraft.departure_time" class="input"></div><div><label class="label">Expected Arrival Date *</label><input type="date" x-model="travelDraft.expected_arrival_date" class="input"></div><div><label class="label">Expected Arrival Time *</label><input type="time" x-model="travelDraft.expected_arrival_time" class="input"></div><div><label class="label">Estimated Expense</label><input type="number" min="0" step="0.01" x-model="travelDraft.estimated_expense" class="input"></div></div>
</div><div class="obv3-modal-foot"><button type="button" @click="travelModalOpen = false" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="saveTravelModal()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Save Travel Details</button></div></div></div>

<div x-show="transportationModalOpen" x-cloak class="obv3-modal-backdrop" @click.self="transportationModalOpen = false"><div class="obv3-modal max-w-2xl"><div class="obv3-modal-head"><h3 class="form-title">Select / Edit Transportation</h3><button type="button" @click="transportationModalOpen = false" class="text-2xl text-gray-500">&times;</button></div><div class="obv3-modal-body space-y-3">
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid"><div><label class="label">Client / Trip</label><input :value="selectedTransportationRow ? detailTripLabel(selectedTransportationRow) : ''" readonly class="input bg-gray-100"></div><div><label class="label">Route</label><input :value="selectedTransportationRow ? detailRoute(selectedTransportationRow) : ''" readonly class="input bg-gray-100"></div></div>
    <div><label class="label">Mode of Transportation *</label><select x-model="transportationDraft.mode" @change="clearTransportationDraftForMode($event.target.value)" class="input"><option value="">Select mode</option><template x-for="mode in transportationModes" :key="mode"><option :value="mode" x-text="transportationModeLabel(mode)"></option></template></select></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid" x-show="transportationDraft.mode === 'Company Vehicle'"><div><label class="label">Vehicle *</label><input x-model="transportationDraft.vehicle" class="input"></div><div><label class="label">Plate Number *</label><input x-model="transportationDraft.plate_number" class="input"></div><div class="col-span-2"><label class="label">Driver *</label><input x-model="transportationDraft.driver" class="input"></div></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid" x-show="transportationDraft.mode === 'Private Vehicle'"><div><label class="label">Vehicle *</label><input x-model="transportationDraft.vehicle" class="input"></div><div><label class="label">Plate Number *</label><input x-model="transportationDraft.plate_number" class="input"></div><div class="col-span-2"><label class="label">Owner / Driver *</label><input x-model="transportationDraft.owner_driver" class="input"></div></div>
    <div x-show="transportationDraft.mode === 'Ride-Hailing'"><label class="label">Provider *</label><input x-model="transportationDraft.provider" class="input"></div>
    <div x-show="transportationDraft.mode === 'Taxi'"><label class="label">Taxi Details (optional)</label><textarea x-model="transportationDraft.other_details" rows="2" class="input"></textarea></div>
    <div x-show="transportationDraft.mode === 'Public Transportation'"><label class="label">Type of Transport *</label><input x-model="transportationDraft.transport_type" placeholder="Bus, train, jeepney, etc." class="input"></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid" x-show="transportationDraft.mode === 'Rental Vehicle'"><div><label class="label">Rental Provider *</label><input x-model="transportationDraft.rental_provider" class="input"></div><div><label class="label">Vehicle *</label><input x-model="transportationDraft.vehicle" class="input"></div><div class="col-span-2"><label class="label">Plate Number *</label><input x-model="transportationDraft.plate_number" class="input"></div></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid" x-show="transportationDraft.mode === 'Air'"><div><label class="label">Airline *</label><input x-model="transportationDraft.airline" class="input"></div><div><label class="label">Flight Number *</label><input x-model="transportationDraft.flight_number" class="input"></div></div>
    <div class="grid grid-cols-2 gap-3 obv3-modal-grid" x-show="transportationDraft.mode === 'Sea/Ferry'"><div><label class="label">Shipping Line *</label><input x-model="transportationDraft.shipping_line" class="input"></div><div><label class="label">Vessel / Trip *</label><input x-model="transportationDraft.vessel_trip" class="input"></div></div>
    <div x-show="transportationDraft.mode === 'Walking'" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">No additional transportation details required.</div>
    <div x-show="transportationDraft.mode === 'Other'"><label class="label">Specify *</label><input x-model="transportationDraft.other_details" class="input"></div>
</div><div class="obv3-modal-foot"><button type="button" @click="transportationModalOpen = false" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="saveTransportationModal()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Save Transportation</button></div></div></div>

<div x-show="attachmentModalOpen" x-cloak class="obv3-modal-backdrop" @click.self="cancelCreateAttachment()"><div class="obv3-modal max-w-2xl"><div class="obv3-modal-head"><h3 class="form-title">Add Attachment</h3><button type="button" @click="cancelCreateAttachment()" class="text-2xl text-gray-500">&times;</button></div><div class="obv3-modal-body space-y-3">
    <template x-if="form.create_attachments[attachmentModalIndex]"><div class="space-y-3"><div><label class="label">Attachment Type *</label><select x-model="form.create_attachments[attachmentModalIndex].type" class="input"><option value="">Select type</option><template x-for="type in createAttachmentTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div><div><label class="label">Related Client / Trip</label><select x-model="form.create_attachments[attachmentModalIndex].detail_key" class="input"><option value="">General O.B.</option><template x-for="row in form.detail_rows || []" :key="detailReference(row)"><option :value="detailReference(row)" x-text="attachmentTripLabel(row)"></option></template></select></div><div><label class="label">Upload File *</label><label :for="`ob-attachment-file-${form.create_attachments[attachmentModalIndex]?.key}`" class="inline-block cursor-pointer rounded-lg border px-3 py-2 text-sm">Choose File</label><p x-show="form.create_attachments[attachmentModalIndex]?.filename" class="text-xs text-gray-500" x-text="form.create_attachments[attachmentModalIndex]?.filename"></p></div><div><label class="label">Description</label><textarea x-model="form.create_attachments[attachmentModalIndex].description" rows="3" class="input" placeholder="Optional description"></textarea></div></div></template>
</div><div class="obv3-modal-foot"><button type="button" @click="cancelCreateAttachment()" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="saveCreateAttachment()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Add Attachment</button></div></div></div>
