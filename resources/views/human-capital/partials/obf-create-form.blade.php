<section class="form-section" x-show="!isEdit || Number(form.workflow_version || 3) >= 3" x-cloak>
    <h3 class="form-title">O.B. Classification</h3>
    <p class="text-sm text-gray-600 mb-3">What is this Official Business for?</p>
    <div class="grid grid-cols-3 gap-2">
        <template x-for="option in classifications" :key="option">
            <button type="button" @click="changeClassification(option)" :disabled="isView"
                :aria-pressed="form.classification === option"
                :class="form.classification === option ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-blue-50'"
                class="rounded-lg border p-3 text-xs font-semibold disabled:opacity-60" x-text="classificationLabel(option)"></button>
        </template>
    </div>
</section>

<div x-show="form.classification" x-cloak>
    <section class="form-section">
        <h3 class="form-title">Personnel Details</h3>
        <div class="overflow-x-auto"><table class="w-full text-xs border-collapse">
            <thead class="bg-blue-50 text-blue-900"><tr><th class="p-2 text-left">Employee</th><th class="p-2">Employee ID</th><th class="p-2">Position</th><th class="p-2">Department / Unit</th><th class="p-2">Nature of Travel</th><th class="p-2">Immediate Supervisor</th><th class="p-2">Role</th><th class="p-2">Action</th></tr></thead>
            <tbody>
                <tr class="border-b">
                    <td class="p-2"><span x-text="preview.full_name || form.employee_name || '-'"></span><template x-if="canManageObf && !isView"><select x-model="form.employee_id" @change="selectEmployee(); removePrimaryFromTeam()" class="input mt-1"><template x-for="employee in employees" :key="employee.id"><option :value="employee.id" x-text="employee.full_name"></option></template></select></template></td>
                    <td class="p-2" x-text="preview.employee_code || form.employee_code || '-'"></td><td class="p-2" x-text="preview.position || '-'"></td><td class="p-2" x-text="preview.department || '-'"></td>
                    <td class="p-2"><select x-model="form.nature_of_travel" :disabled="isView" class="input"><option value="">Select</option><option>Individual</option><option>Team</option></select></td>
                    <td class="p-2" x-text="form.immediate_superior || '-'"></td><td class="p-2 font-semibold">Primary Traveler</td><td class="p-2 text-gray-400">—</td>
                </tr>
                <template x-for="(member, index) in form.team_rows || []" :key="member.employee_id"><tr class="border-b">
                    <td class="p-2" x-text="member.full_name"></td><td class="p-2" x-text="member.employee_code"></td><td class="p-2" x-text="member.position || '-'"></td><td class="p-2" x-text="member.department || '-'"></td>
                    <td class="p-2" x-text="member.nature_of_travel || '-'"></td><td class="p-2" x-text="member.immediate_superior || '-'"></td><td class="p-2">Team Member</td>
                    <td class="p-2"><button type="button" x-show="!isView" @click="removeTeamMember(index)" class="text-red-600">Remove</button></td>
                </tr></template>
            </tbody>
        </table></div>
        <button type="button" x-show="!isView" @click="teamModalOpen = true; teamDraft = {employee_id:'',nature_of_travel:''}" class="mt-3 px-3 py-2 rounded-lg border border-blue-600 text-blue-700 text-xs font-semibold">+ Add Team Member</button>
    </section>

    <section class="form-section">
        <div class="flex items-center justify-between gap-2"><h3 class="form-title" x-text="detailSectionTitle()"></h3><button type="button" x-show="!isView" @click="openDetailModal()" class="px-3 py-2 rounded-lg border border-blue-600 text-blue-700 text-xs font-semibold" x-text="detailAddLabel()"></button></div>
        <div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-blue-50 text-blue-900"><tr><th class="p-2" x-text="form.classification === 'client' ? 'Client' : form.classification === 'social' ? 'Activity / Event' : 'From'"></th><th class="p-2">From</th><th class="p-2">To / Destination</th><th class="p-2">Purpose</th><th class="p-2" x-text="form.classification === 'client' ? 'Type' : 'Activity Type'"></th><th class="p-2" x-text="form.classification === 'client' ? 'Related Engagement' : form.classification === 'internal' ? 'Related Department / Office' : 'Organization / Host'"></th><th class="p-2" x-show="form.classification === 'client'">Travel Credit Balance</th><th class="p-2" x-show="form.classification === 'internal'">Related Internal Record</th><th class="p-2" x-show="form.classification === 'social'">Related Contact</th><th class="p-2">Action</th></tr></thead><tbody>
            <template x-for="(row, index) in form.detail_rows || []" :key="row.id || index"><tr class="border-b"><td class="p-2" x-text="detailHeading(row)"></td><td class="p-2" x-text="row.from_location || '-'"></td><td class="p-2" x-text="row.destination || '-'"></td><td class="p-2" x-text="row.purpose || '-'"></td><td class="p-2" x-text="row.engagement_type || row.internal_activity_type || row.social_activity_type || '-'"></td><td class="p-2" x-text="detailRelated(row)"></td><td class="p-2" x-show="form.classification === 'client'" x-text="row.travel_credit_balance ?? '—'"></td><td class="p-2" x-show="form.classification === 'internal'" x-text="row.related_internal_type && row.related_internal_id ? `${row.related_internal_type} #${row.related_internal_id}` : '-'"></td><td class="p-2" x-show="form.classification === 'social'" x-text="contactName(row.contact_id) || row.related_contact_text || '-'"></td><td class="p-2 whitespace-nowrap"><button type="button" x-show="!isView" @click="moveDetail(index, -1)" :disabled="index === 0" class="text-gray-600 mr-1">Up</button><button type="button" x-show="!isView" @click="moveDetail(index, 1)" :disabled="index === form.detail_rows.length - 1" class="text-gray-600 mr-2">Down</button><button type="button" x-show="!isView" @click="openDetailModal(index)" class="text-blue-700 mr-2">Edit</button><button type="button" x-show="!isView" @click="removeDetail(index)" class="text-red-600">Remove</button></td></tr></template>
            <tr x-show="!(form.detail_rows || []).length"><td colspan="10" class="p-3 text-center text-gray-500">No O.B. Detail entries yet.</td></tr>
        </tbody></table></div>
    </section>
</div>

<div x-show="form.classification && (form.detail_rows || []).length" x-cloak class="space-y-4">
    <section class="form-section"><h3 class="form-title">Travel Details</h3><p class="mb-3 text-xs text-gray-500">Client and route are read-only and follow each O.B. Detail.</p>
        <div class="space-y-4"><template x-for="(row, index) in form.detail_rows || []" :key="`travel-${row.id || index}`"><div class="rounded-lg border border-gray-200 p-4">
            <div class="mb-3 grid grid-cols-2 gap-3 text-xs"><div><b>Client / Trip:</b> <span x-text="detailTripLabel(row)"></span></div><div><b>Route:</b> <span x-text="`${row.from_location || '-'} → ${row.destination || '-'}`"></span></div></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label">Departure Date *</label><input type="date" x-model="row.travel.departure_date" :readonly="isView" class="input"></div><div><label class="label">Departure Time *</label><input type="time" x-model="row.travel.departure_time" :readonly="isView" class="input"></div><div><label class="label">Expected Arrival Date *</label><input type="date" x-model="row.travel.expected_arrival_date" :readonly="isView" class="input"></div><div><label class="label">Expected Arrival Time *</label><input type="time" x-model="row.travel.expected_arrival_time" :readonly="isView" class="input"></div><div><label class="label">Estimated Expense</label><input type="number" min="0" step="0.01" x-model="row.travel.estimated_expense" :readonly="isView" class="input"></div></div>
        </div></template></div>
    </section>
    <section class="form-section"><h3 class="form-title">Mode of Transportation</h3><p class="mb-3 text-xs text-gray-500">Choose one mode for each detail. Required fields are checked when submitting.</p>
        <div class="space-y-4"><template x-for="(row, index) in form.detail_rows || []" :key="`transport-${row.id || index}`"><div class="rounded-lg border border-gray-200 p-4">
            <div class="mb-3 grid grid-cols-2 gap-3 text-xs"><div><b>Client / Trip:</b> <span x-text="detailTripLabel(row)"></span></div><div><b>Route:</b> <span x-text="`${row.from_location || '-'} → ${row.destination || '-'}`"></span></div></div>
            <label class="label">Mode of Transportation *</label><select x-model="row.transportation.mode" @change="clearTransportationForMode(index, $event.target.value)" :disabled="isView" class="input bg-white"><option value="">Select mode</option><template x-for="mode in ['Company Vehicle','Private Vehicle','Ride-Hailing','Taxi','Public Transportation','Rental Vehicle','Air','Sea/Ferry','Walking','Other']" :key="mode"><option :value="mode" x-text="mode"></option></template></select>
            <div class="mt-3 grid grid-cols-2 gap-3" x-show="row.transportation.mode === 'Company Vehicle'"><div><label class="label">Vehicle *</label><input x-model="row.transportation.vehicle" :readonly="isView" class="input"></div><div><label class="label">Plate Number *</label><input x-model="row.transportation.plate_number" :readonly="isView" class="input"></div><div><label class="label">Driver *</label><input x-model="row.transportation.driver" :readonly="isView" class="input"></div></div>
            <div class="mt-3 grid grid-cols-2 gap-3" x-show="row.transportation.mode === 'Private Vehicle'"><div><label class="label">Vehicle *</label><input x-model="row.transportation.vehicle" :readonly="isView" class="input"></div><div><label class="label">Plate Number *</label><input x-model="row.transportation.plate_number" :readonly="isView" class="input"></div><div><label class="label">Owner / Driver *</label><input x-model="row.transportation.owner_driver" :readonly="isView" class="input"></div></div>
            <div class="mt-3" x-show="row.transportation.mode === 'Ride-Hailing'"><label class="label">Provider *</label><input x-model="row.transportation.provider" :readonly="isView" class="input"></div>
            <div class="mt-3" x-show="row.transportation.mode === 'Taxi'"><label class="label">Taxi Details (optional)</label><textarea x-model="row.transportation.other_details" :readonly="isView" class="input" rows="2"></textarea></div>
            <div class="mt-3" x-show="row.transportation.mode === 'Public Transportation'"><label class="label">Transportation Type *</label><input x-model="row.transportation.transport_type" :readonly="isView" class="input" placeholder="Bus, train, jeepney, etc."></div>
            <div class="mt-3 grid grid-cols-2 gap-3" x-show="row.transportation.mode === 'Rental Vehicle'"><div><label class="label">Rental Provider *</label><input x-model="row.transportation.rental_provider" :readonly="isView" class="input"></div><div><label class="label">Vehicle *</label><input x-model="row.transportation.vehicle" :readonly="isView" class="input"></div><div><label class="label">Plate Number *</label><input x-model="row.transportation.plate_number" :readonly="isView" class="input"></div></div>
            <div class="mt-3 grid grid-cols-2 gap-3" x-show="row.transportation.mode === 'Air'"><div><label class="label">Airline *</label><input x-model="row.transportation.airline" :readonly="isView" class="input"></div><div><label class="label">Flight Number *</label><input x-model="row.transportation.flight_number" :readonly="isView" class="input"></div></div>
            <div class="mt-3 grid grid-cols-2 gap-3" x-show="row.transportation.mode === 'Sea/Ferry'"><div><label class="label">Shipping Line *</label><input x-model="row.transportation.shipping_line" :readonly="isView" class="input"></div><div><label class="label">Vessel / Trip *</label><input x-model="row.transportation.vessel_trip" :readonly="isView" class="input"></div></div>
            <div class="mt-3" x-show="row.transportation.mode === 'Other'"><label class="label">Specify *</label><input x-model="row.transportation.other_details" :readonly="isView" class="input"></div><p class="mt-3 text-xs text-gray-500" x-show="row.transportation.mode === 'Walking'">No additional details needed.</p>
        </div></template></div>
    </section>
</div>

<section class="form-section" x-show="form.classification" x-cloak>
    <div class="flex items-center justify-between gap-3">
        <div><h3 class="form-title">Attachments</h3><p class="text-xs text-gray-500">Link files to General O.B. or a specific detail.</p></div>
        <button type="button" x-show="!isView" @click="addCreateAttachment()" class="rounded-lg border border-blue-600 px-3 py-2 text-xs font-semibold text-blue-700">+ Add Attachment</button>
    </div>
    <div class="mt-3 space-y-3">
        <template x-for="(legacyFile, legacyIndex) in form.attachment_paths || []" :key="legacyFile.path || legacyIndex">
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs"><div class="min-w-0"><div class="font-semibold" x-text="legacyFile.type || form.attachment_types?.[legacyIndex] || 'Existing O.B. attachment'"></div><div class="truncate text-gray-600" x-text="legacyFile.original_name || 'Previously uploaded file'"></div><div class="text-gray-500">General O.B.</div></div><a :href="legacyFile.url" target="_blank" rel="noopener" class="shrink-0 text-blue-700">Open</a></div>
        </template>
        <template x-for="(attachment, index) in form.create_attachments || []" :key="attachment.id || attachment.key">
            <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-2" x-show="!attachment.remove">
                <div><label class="label">Type *</label><select x-model="attachment.type" :disabled="isView" class="input"><option value="">Select type</option><template x-for="type in createAttachmentTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div>
                <div><label class="label">Related Client / Trip</label><select x-model="attachment.detail_key" :disabled="isView" class="input"><option value="">General O.B.</option><template x-for="row in form.detail_rows || []" :key="detailReference(row)"><option :value="detailReference(row)" x-text="attachmentTripLabel(row)"></option></template></select></div>
                <div><label class="label">File <span x-show="!attachment.id">*</span></label><input type="file" :name="`attachment_files[${index}]`" :disabled="isView" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.mp3,.wav,.m4a,.mp4,.mov" class="input" @change="setCreateAttachmentFile(index, $event)"><p class="mt-1 text-xs text-gray-500" x-show="attachment.filename" x-text="attachment.filename"></p></div>
                <div><label class="label">Description</label><input x-model="attachment.description" :readonly="isView" maxlength="1000" class="input"></div>
                <div class="flex items-center justify-between gap-2 md:col-span-2"><a x-show="attachment.id && attachment.url" :href="attachment.url" target="_blank" rel="noopener" class="truncate text-xs text-blue-700" x-text="attachment.filename || 'Open file'"></a><button type="button" x-show="!isView" @click="removeCreateAttachment(index)" class="ml-auto text-xs font-semibold text-red-600">Remove</button></div>
            </div>
            <div class="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs" x-show="attachment.remove"><span x-text="`Remove ${attachment.filename || 'this attachment'}? It will be deleted when saved.`"></span><button type="button" @click="attachment.remove = false; dirty = true" class="font-semibold text-blue-700">Undo</button></div>
        </template>
        <p x-show="!(form.create_attachments || []).length" class="text-xs text-gray-500">No attachments added.</p>
    </div>
</section>

<section class="form-section" x-show="form.classification" x-cloak>
    <h3 class="form-title">Remarks</h3>
    <textarea name="remarks" x-model="form.remarks" :readonly="isView" rows="4" class="input"></textarea>
</section>

<div x-show="teamModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><div class="bg-white rounded-xl shadow-xl p-5 w-full max-w-lg space-y-3">
    <h3 class="form-title">Add Team Member</h3>
    <label class="label">Employee *</label><select x-model="teamDraft.employee_id" class="input"><option value="">Select active employee</option><template x-for="employee in personnelEmployees" :key="employee.id"><option :value="employee.id" :disabled="String(employee.id) === String(form.employee_id) || (form.team_rows || []).some(row => String(row.employee_id) === String(employee.id))" x-text="employee.full_name"></option></template></select>
    <div class="grid grid-cols-2 gap-2 text-xs"><div>Employee ID: <span x-text="selectedTeamEmployee()?.employee_code || '-'"></span></div><div>Position: <span x-text="selectedTeamEmployee()?.position || '-'"></span></div><div>Department / Unit: <span x-text="selectedTeamEmployee()?.department || '-'"></span></div><div>Immediate Supervisor: <span x-text="selectedTeamEmployee()?.immediate_superior || '-'"></span></div></div>
    <label class="label">Nature of Travel *</label><select x-model="teamDraft.nature_of_travel" class="input"><option value="">Select nature</option><option>Individual</option><option>Team</option></select>
    <div class="flex justify-end gap-2"><button type="button" @click="teamModalOpen = false" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="addTeamMember()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Add Team Member</button></div>
</div></div>

<div x-show="detailModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><div class="bg-white rounded-xl shadow-xl p-5 w-full max-w-2xl max-h-[90vh] overflow-y-auto space-y-3">
    <h3 class="form-title" x-text="detailModalIndex === null ? detailAddLabel() : 'Edit O.B. Detail'"></h3>
    <div x-show="form.classification === 'client'"><label class="label">Client *</label><select x-model="detailDraft.company_id" @change="refreshEngagements()" class="input"><option value="">Select client</option><template x-for="company in companies" :key="company.id"><option :value="company.id" x-text="company.company_name"></option></template></select></div>
    <div x-show="form.classification === 'social'"><label class="label">Activity / Event Name *</label><input x-model="detailDraft.activity_event_name" class="input"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label">From *</label><input x-model="detailDraft.from_location" list="ob-from-suggestions" @focus="loadSuggestions('from')" class="input"></div><div><label class="label">To / Destination *</label><input x-model="detailDraft.destination" list="ob-destination-suggestions" @focus="loadSuggestions('destination')" class="input"></div></div>
    <label class="label">Purpose *</label><input x-model="detailDraft.purpose" list="ob-purpose-suggestions" @focus="loadSuggestions('purpose')" class="input">
    <template x-for="field in ['from','destination','purpose']" :key="field"><datalist :id="`ob-${field}-suggestions`"><template x-for="value in suggestions[field] || []" :key="value"><option :value="value"></option></template></datalist></template>
    <div x-show="form.classification === 'client'" class="grid grid-cols-2 gap-3"><div><label class="label">Type *</label><select x-model="detailDraft.engagement_type" @change="refreshEngagements()" class="input"><option value="">Select type</option><option>Regular</option><option>Project</option></select></div><div><label class="label">Related Engagement *</label><select x-model="detailDraft.project_id" class="input"><option value="">Select engagement</option><template x-for="engagement in engagements" :key="engagement.id"><option :value="engagement.id" x-text="engagement.name"></option></template></select></div><div class="col-span-2"><label class="label">Travel Credit Balance</label><input :value="detailDraft.travel_credit_balance ?? '—'" readonly class="input bg-gray-100"></div></div>
    <div x-show="form.classification === 'internal'" class="grid grid-cols-2 gap-3"><div><label class="label">Internal Activity Type *</label><select x-model="detailDraft.internal_activity_type" class="input"><option value="">Select type</option><template x-for="type in internalActivityTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div><div><label class="label">Related Department / Office *</label><select x-model="detailDraft.department_id" class="input"><option value="">Select department</option><template x-for="department in departments" :key="department.id"><option :value="department.id" x-text="department.department_name"></option></template></select></div><div><label class="label">Related Internal Record (optional)</label><input x-model="detailDraft.related_internal_type" placeholder="Record type" class="input"></div><div><label class="label">Record ID</label><input type="number" min="1" x-model="detailDraft.related_internal_id" class="input"></div></div>
    <div x-show="form.classification === 'social'" class="grid grid-cols-2 gap-3"><div><label class="label">Activity Type *</label><select x-model="detailDraft.social_activity_type" class="input"><option value="">Select type</option><template x-for="type in socialActivityTypes" :key="type"><option :value="type" x-text="type"></option></template></select></div><div><label class="label">Organization / Host</label><input x-model="detailDraft.organization_host" class="input"></div><div><label class="label">Related Contact</label><select x-model="detailDraft.contact_id" class="input"><option value="">Select contact</option><template x-for="contact in contacts" :key="contact.id"><option :value="contact.id" x-text="contact.label"></option></template></select></div><div><label class="label">Or contact text</label><input x-model="detailDraft.related_contact_text" class="input"></div></div>
    <div class="flex justify-end gap-2"><button type="button" @click="detailModalOpen = false" class="px-3 py-2 border rounded-lg">Cancel</button><button type="button" @click="saveDetail()" class="px-3 py-2 bg-blue-600 text-white rounded-lg">Save Detail</button></div>
</div></div>
