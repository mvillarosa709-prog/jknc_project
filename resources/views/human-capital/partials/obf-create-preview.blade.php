<div class="section-title">O.B. Classification</div>
<table class="preview-table text-xs"><tr><td x-text="classificationLabel()"></td></tr></table>
<div class="section-title">Personnel Details</div>
<table class="preview-table text-xs">
    <tr><th>Role</th><th>Employee</th><th>Position / Department</th><th>Nature</th></tr>
    <tr><td>Primary Traveler</td><td x-text="preview.full_name || form.employee_name || '-' "></td><td x-text="[preview.position, preview.department].filter(Boolean).join(' / ') || '-'"></td><td x-text="form.nature_of_travel || '-'"></td></tr>
    <template x-for="member in form.team_rows || []" :key="member.employee_id">
        <tr><td>Team Member</td><td x-text="member.full_name"></td><td x-text="[member.position, member.department].filter(Boolean).join(' / ') || '-'"></td><td x-text="member.nature_of_travel || '-'"></td></tr>
    </template>
</table>
<div class="section-title">Travel Details & Transportation</div>
<table class="preview-table text-xs">
    <tr><th>Client / Trip</th><th>Route</th><th>Departure</th><th>Expected Arrival</th><th>Estimated Expense</th><th>Transportation</th><th>Mode Details</th></tr>
    <template x-for="(row, index) in form.detail_rows || []" :key="`preview-travel-${row.id || index}`">
        <tr>
            <td x-text="detailTripLabel(row)"></td>
            <td x-text="`${row.from_location || '-'} → ${row.destination || '-'}`"></td>
            <td x-text="[row.travel?.departure_date, row.travel?.departure_time].filter(Boolean).join(' ') || '-' "></td>
            <td x-text="[row.travel?.expected_arrival_date, row.travel?.expected_arrival_time].filter(Boolean).join(' ') || '-' "></td>
            <td x-text="row.travel?.estimated_expense === 0 || row.travel?.estimated_expense ? row.travel.estimated_expense : '-' "></td>
            <td x-text="row.transportation?.mode || '-' "></td>
            <td x-text="transportationDescription(row.transportation || {})"></td>
        </tr>
    </template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="7">No O.B. Details yet.</td></tr>
</table>
<div class="section-title" x-text="detailSectionTitle()"></div>
<table class="preview-table text-xs">
    <tr><th x-text="form.classification === 'client' ? 'Client' : form.classification === 'social' ? 'Activity / Event' : 'Activity'"></th><th>Route / Purpose</th><th>Details</th></tr>
    <template x-for="(row, index) in form.detail_rows || []" :key="row.id || index">
        <tr>
            <td x-text="detailHeading(row)"></td>
            <td><span x-text="`${row.from_location || '-'} → ${row.destination || '-'}`"></span><br><span x-text="row.purpose || '-'"></span></td>
            <td x-text="detailDescription(row)"></td>
        </tr>
    </template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="3">No O.B. Details yet.</td></tr>
</table>
<div class="section-title">Attachments</div>
<table class="preview-table text-xs">
    <tr><th>Type</th><th>Related Client / Trip</th><th>File</th><th>Description</th></tr>
    <template x-for="(attachment, index) in (form.create_attachments || []).filter(item => !item.remove)" :key="attachment.id || attachment.key || index">
        <tr>
            <td x-text="attachment.type || '-' "></td>
            <td x-text="attachment.detail_key ? (form.detail_rows || []).map(row => detailReference(row) === attachment.detail_key ? attachmentTripLabel(row) : '').find(Boolean) || 'Unmatched detail' : 'General O.B.'"></td>
            <td x-text="attachment.filename || 'File pending'"></td>
            <td x-text="attachment.description || '-' "></td>
        </tr>
    </template>
    <template x-for="(attachment, index) in form.attachment_paths || []" :key="`existing-general-${index}`"><tr><td x-text="attachment.type || form.attachment_types?.[index] || 'Existing attachment'"></td><td>General O.B.</td><td x-text="attachment.original_name || 'Previously uploaded file'"></td><td>-</td></tr></template>
    <tr x-show="!(form.create_attachments || []).some(item => !item.remove) && !(form.attachment_paths || []).length"><td colspan="4">No attachments added.</td></tr>
</table>
<div class="section-title">Remarks</div>
<table class="preview-table text-xs"><tr><td class="whitespace-pre-line" x-text="form.remarks || '-' "></td></tr></table>
