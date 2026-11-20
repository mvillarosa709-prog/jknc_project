<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\Tenant;
use App\Services\Records\RecordLookups;
use Illuminate\Database\Seeder;

class RecordClassificationSeeder extends Seeder
{
    /**
     * Classifications and their standard indexes, keyed by classification code.
     *
     * @var array<string, array{name: string, description: string, indexes: array<string, string>}>
     */
    protected array $scheme = [
        '101' => [
            'name' => 'Client Master File',
            'description' => 'Client master file and general client correspondence.',
            'indexes' => [],
        ],
        '201' => [
            'name' => 'Employee Personnel File',
            'description' => 'Official employment and personnel records.',
            'indexes' => [
                '201-1' => 'Pre-Employment and Hiring Records',
                '201-2' => 'Employment Memos and Personnel Notices',
            ],
        ],
        '301' => [
            'name' => 'Entity & Ownership',
            'description' => 'Entity incorporation, ownership, and governance records.',
            'indexes' => [
                '301-1' => 'Incorporation',
                '301-2' => 'Articles of Incorporation',
                '301-6' => 'GIS',
                '301-12' => 'Minutes',
                '301-13' => 'Board Resolution',
                '301-15' => 'Sec Cert',
            ],
        ],
        '401' => [
            'name' => 'BIR & Tax',
            'description' => 'Bureau of Internal Revenue and tax registration records.',
            'indexes' => [
                '401-4' => 'Form 2303',
                '401-15' => 'Form 1601C',
                '401-30' => 'Form 2307',
            ],
        ],
        '601' => [
            'name' => 'Local Government',
            'description' => 'Local permits, licenses and government records.',
            'indexes' => [
                '601-2' => 'Business Permits and Licensing Office',
            ],
        ],
        '701' => [
            'name' => 'Finance',
            'description' => 'Financial statements and finance records.',
            'indexes' => [
                '701-5' => 'Financial Statements and Supporting Schedules',
            ],
        ],
        '801' => [
            'name' => 'Banking',
            'description' => 'Banking records and account documentation.',
            'indexes' => [
                '801-1' => 'Bank Account Opening and Maintenance Records',
            ],
        ],
        '901' => [
            'name' => 'Legal',
            'description' => 'Legal records, contracts, and case documentation.',
            'indexes' => [
                '901-1' => 'Contracts',
                '901-2' => 'Agreements',
            ],
        ],
        '1001' => [
            'name' => 'Operations',
            'description' => 'Operational and administrative records.',
            'indexes' => [
                '1001-3' => 'Operational Policies, Procedures and SOP Records',
                '1001-27' => 'Document Control and Records Administration',
            ],
        ],
        '1101' => [
            'name' => 'Real Property Records',
            'description' => 'Property ownership and technical records.',
            'indexes' => [
                '1101-1' => 'Land Titles and Ownership Records',
            ],
        ],
        '2001' => [
            'name' => 'Correspondence',
            'description' => 'Official written communications.',
            'indexes' => [
                '2001-1' => 'Letters',
            ],
        ],
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

        if ($companies->isEmpty()) {
            $companies = collect([Company::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Default Company',
                'code' => 'DEFAULT',
                'business_name' => 'Default Company',
            ])]);
        }

        foreach ($companies as $company) {
            $this->seedForCompany($tenant->id, $company);
        }
    }

    private function seedForCompany(string $tenantId, Company $company): void
    {
        // The scheme this seeder is about to rewrite is cached per company, so the
        // cached copy has to go before the next request can read it.
        RecordLookups::forgetFor($tenantId, (string) $company->id);

        foreach ($this->scheme as $classificationCode => $definition) {
            $classification = RecordClassification::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'company_id' => $company->id,
                    'code' => $classificationCode,
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            $indexSortOrder = 0;

            foreach ($definition['indexes'] as $indexCode => $indexName) {
                RecordIndex::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $company->id,
                        'record_classification_id' => $classification->id,
                        'code' => $indexCode,
                    ],
                    [
                        'name' => $indexName,
                        'sort_order' => $indexSortOrder++,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
