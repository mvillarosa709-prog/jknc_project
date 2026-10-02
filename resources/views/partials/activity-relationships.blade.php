<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <label class="block text-xs text-slate-600">Company
        <select x-model="{{ $relationshipForm }}.companyId" class="mt-1 h-10 w-full rounded border border-slate-200 bg-white px-2">
            <option value="">No company</option>
            <template x-for="record in messageCompanyRecords" :key="record.id">
                <option :value="String(record.id)" x-text="record.label + ' (#' + record.id + ')' "></option>
            </template>
        </select>
    </label>
    <label class="block text-xs text-slate-600">Contact
        <select x-model="{{ $relationshipForm }}.contactId" @change="selectRelationshipContact({{ $relationshipForm }})" class="mt-1 h-10 w-full rounded border border-slate-200 bg-white px-2">
            <option value="">No contact</option>
            <template x-for="contact in contacts" :key="contact.id">
                <option :value="String(contact.id)" x-text="(contact.label || contact.name) + ' (#' + contact.id + ')' "></option>
            </template>
        </select>
        <span class="block mt-1" x-show="relationshipNeedsCompany({{ $relationshipForm }})">This contact has multiple companies. Select the appropriate company if needed.</span>
    </label>
</div>
