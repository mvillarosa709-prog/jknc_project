<div class="section-title">Personnel Details</div>
<table class="preview-table text-xs">
    <tr><th>Employee</th><th>Position</th><th>Department</th><th>Nature</th></tr>
    <tr><td x-text="preview.full_name || form.employee_name || '-' "></td><td x-text="preview.position || '-' "></td><td x-text="preview.department || '-' "></td><td x-text="form.nature_of_travel || '-' "></td></tr>
    <template x-for="member in form.team_rows || []" :key="member.employee_id"><tr><td x-text="member.full_name"></td><td x-text="member.position || '-' "></td><td x-text="member.department || '-' "></td><td x-text="member.nature_of_travel || '-' "></td></tr></template>
</table>
<div class="section-title" x-text="detailSectionTitle()"></div>
<table class="preview-table text-xs" x-show="form.classification === 'client'"><tr><th>Client</th><th>Route</th><th>Purpose</th><th>Engagement</th><th>Travel Credit</th></tr>
    <template x-for="row in form.detail_rows || []" :key="row.id || row.client_key"><tr><td x-text="clientName(row.company_id)"></td><td x-text="detailRoute(row)"></td><td x-text="row.purpose || '-' "></td><td x-text="detailRelated(row)"></td><td x-text="row.travel_credit_balance ?? '—'"></td></tr></template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="5">—</td></tr>
</table>
<table class="preview-table text-xs" x-show="form.classification === 'internal'"><tr><th>Route</th><th>Purpose</th><th>Activity Type</th><th>Department / Office</th></tr>
    <template x-for="row in form.detail_rows || []" :key="row.id || row.client_key"><tr><td x-text="detailRoute(row)"></td><td x-text="row.purpose || '-' "></td><td x-text="row.internal_activity_type || '-' "></td><td x-text="departmentName(row.department_id)"></td></tr></template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="4">—</td></tr>
</table>
<table class="preview-table text-xs" x-show="form.classification === 'social'"><tr><th>Activity / Event</th><th>Route</th><th>Purpose</th><th>Activity Type</th></tr>
    <template x-for="row in form.detail_rows || []" :key="row.id || row.client_key"><tr><td x-text="row.activity_event_name || '-' "></td><td x-text="detailRoute(row)"></td><td x-text="row.purpose || '-' "></td><td x-text="row.social_activity_type || '-' "></td></tr></template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="4">—</td></tr>
</table>
<div class="section-title">Travel Details</div>
<table class="preview-table text-xs">
    <tr><th>Client / Trip</th><th>Route</th><th>Departure</th><th>Expected Arrival</th><th>Estimated Expense</th></tr>
    <template x-for="row in form.detail_rows || []" :key="`preview-travel-${row.id || row.client_key}`"><tr>
        <td x-text="detailTripLabel(row)"></td><td x-text="detailRoute(row)"></td>
        <td x-text="[row.travel?.departure_date, row.travel?.departure_time].filter(Boolean).join(' ') || '—'"></td>
        <td x-text="[row.travel?.expected_arrival_date, row.travel?.expected_arrival_time].filter(Boolean).join(' ') || '—'"></td>
        <td x-text="formatExpense(row.travel?.estimated_expense)"></td>
    </tr></template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="5">—</td></tr>
</table>
<div class="section-title">Mode of Transportation</div>
<table class="preview-table text-xs">
    <tr><th>Client / Trip</th><th>Route</th><th>Mode</th><th>Details</th></tr>
    <template x-for="row in form.detail_rows || []" :key="`preview-transport-${row.id || row.client_key}`"><tr><td x-text="detailTripLabel(row)"></td><td x-text="detailRoute(row)"></td><td x-text="row.transportation?.mode ? transportationModeLabel(row.transportation.mode) : '—'"></td><td x-text="transportationDescription(row.transportation || {})"></td></tr></template>
    <tr x-show="!(form.detail_rows || []).length"><td colspan="4">—</td></tr>
</table>
<div class="section-title">Attachments</div>
<table class="preview-table text-xs">
    <tr><th>Type</th><th>Related Client / Trip</th><th>File</th><th>Description</th></tr>
    <template x-for="attachment in (form.create_attachments || []).filter(item => !item.remove)" :key="attachment.id || attachment.key"><tr>
        <td x-text="attachment.type || '-' "></td><td x-text="attachment.detail_key ? attachmentTripLabelForKey(attachment.detail_key) : 'General O.B.'"></td><td x-text="attachment.filename || 'File pending'"></td><td x-text="attachment.description || '-' "></td>
    </tr></template>
    <template x-for="(attachment, index) in form.attachment_paths || []" :key="`existing-general-${index}`"><tr><td x-text="attachment.type || form.attachment_types?.[index] || 'Existing attachment'"></td><td>General O.B.</td><td x-text="attachment.original_name || 'Previously uploaded file'"></td><td>—</td></tr></template>
    <tr x-show="!(form.create_attachments || []).some(item => !item.remove) && !(form.attachment_paths || []).length"><td colspan="4">—</td></tr>
</table>
<div class="section-title">Remarks</div>
<table class="preview-table text-xs"><tr><td class="whitespace-pre-line" x-text="form.remarks || '—' "></td></tr></table>
