<?php

namespace Database\Seeders;

use App\Enums\ReferenceStatus;
use App\Enums\SourceType;
use App\Models\Company;
use App\Models\RecordSource;
use App\Models\Tenant;
use App\Services\Records\RecordLookups;
use Illuminate\Database\Seeder;

class RecordSourceSeeder extends Seeder
{
    /**
     * The standard ORDO source list, matching the UI reference mock.
     *
     * @var array<int, array{name: string, type: SourceType}>
     */
    protected array $sources = [
        ['name' => 'Securities and Exchange Commission', 'type' => SourceType::GovernmentAgency],
        ['name' => 'Bureau of Internal Revenue', 'type' => SourceType::GovernmentAgency],
        ['name' => 'Board of Directors', 'type' => SourceType::Internal],
        ['name' => 'Corporate Secretary', 'type' => SourceType::Internal],
        ['name' => 'Management', 'type' => SourceType::Internal],
        ['name' => 'Employee', 'type' => SourceType::Internal],
        ['name' => 'RCBC', 'type' => SourceType::Bank],
        ['name' => 'BDO', 'type' => SourceType::Bank],
        ['name' => 'Supplier', 'type' => SourceType::ExternalParty],
        ['name' => 'Other', 'type' => SourceType::Other],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'ordo'],
            ['name' => 'ORDO'],
        );

        $companies = Company::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get();

        foreach ($companies as $company) {
            $this->seedForCompany($tenant->id, $company);
        }
    }

    private function seedForCompany(string $tenantId, Company $company): void
    {
        // The source list is cached per company, so the cached copy has to go
        // before the next request can read it.
        RecordLookups::forgetFor($tenantId, (string) $company->id);

        foreach ($this->sources as $index => $source) {
            RecordSource::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'company_id' => $company->id,
                    'name' => $source['name'],
                ],
                [
                    'type' => $source['type'],
                    'status' => ReferenceStatus::Active,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
