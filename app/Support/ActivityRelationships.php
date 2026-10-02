<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Support\Facades\Schema;

class ActivityRelationships
{
    public static function companyIds(?Contact $contact): array
    {
        if (! $contact) {
            return [];
        }
        $ids = collect();
        if (Schema::hasTable('company_contact')) {
            $ids = $ids->merge($contact->companies()->pluck('companies.id'));
        }
        if (Schema::hasColumn('companies', 'primary_contact_id')) {
            $ids = $ids->merge($contact->primaryCompanies()->pluck('id'));
        }
        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public static function companyId(?Contact $contact): ?int
    {
        $ids = self::companyIds($contact);
        return count($ids) === 1 ? $ids[0] : null;
    }
}
