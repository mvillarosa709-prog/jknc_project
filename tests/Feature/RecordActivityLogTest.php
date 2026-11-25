<?php

namespace Tests\Feature;

use App\Enums\LinkRelationship;
use App\Models\Company;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\RecordIntakeItem;
use App\Services\CompanyContext;
use App\Services\Records\RecordFilingService;
use App\Services\Records\RecordIntakeService;
use App\Services\Records\RecordLinkService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\FakeUploads;
use Tests\TestCase;

class RecordActivityLogTest extends TestCase
{
    use FakeUploads;

    private Company $company;

    /** @var array<string, array<string, RecordClassification>> */
    private array $classifications = [];

    /** @var array<string, array<string, RecordIndex>> */
    private array $indexes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->seedTenantAndCompany();
        $this->actingInCompany($this->company);
    }

    public function test_the_audit_log_renders(): void
    {
        $this->record();

        $response = $this->get(route('records.logs'));

        $response->assertOk();
        $response->assertViewIs('records.logs');
        $response->assertSee('Audit Log');
    }

    public function test_the_log_lists_entries_newest_first(): void
    {
        $this->record();

        $entries = $this->get(route('records.logs'))->viewData('entries');

        $this->assertNotEmpty($entries);
        $this->assertSame('record.filed', $entries->first()->action);
    }

    public function test_a_record_action_resolves_to_the_record_subject(): void
    {
        $record = $this->record();

        $this->get(route('records.logs'))
            ->assertOk()
            ->assertSee($record->document_reference)
            ->assertSee('Record');
    }

    /**
     * Intake actions log against the intake item, because no record exists yet.
     */
    public function test_an_intake_action_resolves_to_the_intake_item_subject(): void
    {
        $this->createIntake();

        $this->get(route('records.logs'))
            ->assertOk()
            ->assertSee('Intake Item')
            ->assertSee('intake.created');
    }

    public function test_every_action_type_appears_in_the_filter_dropdown(): void
    {
        $this->record();
        $this->createIntake();

        $actions = $this->get(route('records.logs'))->viewData('actions');

        $this->assertContains('record.filed', $actions);
        $this->assertContains('intake.created', $actions);
        $this->assertContains('intake.file_uploaded', $actions);
    }

    public function test_the_action_dropdown_only_offers_actions_that_exist(): void
    {
        $this->record();

        $actions = $this->get(route('records.logs'))->viewData('actions');

        // Nothing in this test has archived or linked anything.
        $this->assertNotContains('record.archived', $actions);
        $this->assertNotContains('record.linked', $actions);
    }

    public function test_it_filters_by_action_type(): void
    {
        $this->createIntake();
        $this->record();

        $response = $this->get(route('records.logs', ['action' => 'record.filed']));

        $response->assertOk();

        $entries = $response->viewData('entries');

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $this->assertSame('record.filed', $entry->action);
        }
    }

    public function test_it_searches_action_and_description(): void
    {
        $this->record();

        $response = $this->get(route('records.logs', ['q' => 'record.filed']));

        $response->assertOk();
        $this->assertNotEmpty($response->viewData('entries'));
    }

    public function test_a_search_that_matches_nothing_returns_nothing(): void
    {
        $this->record();

        $entries = $this->get(route('records.logs', ['q' => 'no such entry anywhere']))
            ->viewData('entries');

        $this->assertCount(0, $entries);
    }

    public function test_it_filters_by_a_date_range(): void
    {
        $this->record();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $this->assertNotEmpty($this->get(route('records.logs', ['from' => $today]))->viewData('entries'));
        $this->assertCount(0, $this->get(route('records.logs', ['from' => $tomorrow]))->viewData('entries'));
        $this->assertCount(0, $this->get(route('records.logs', ['to' => $yesterday]))->viewData('entries'));
    }

    public function test_the_date_range_bounds_are_inclusive(): void
    {
        $this->record();

        $today = now()->format('Y-m-d');

        $entries = $this->get(route('records.logs', ['from' => $today, 'to' => $today]))->viewData('entries');

        $this->assertNotEmpty($entries);
    }

    public function test_it_filters_by_a_single_date(): void
    {
        $this->record();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $this->assertNotEmpty($this->get(route('records.logs', ['date' => $today]))->viewData('entries'));
        $this->assertCount(0, $this->get(route('records.logs', ['date' => $yesterday]))->viewData('entries'));
    }

    public function test_the_drawer_payload_carries_entry_properties(): void
    {
        $this->record();

        $entries = $this->get(route('records.logs'))->viewData('drawerEntries');

        $this->assertNotEmpty($entries);

        $filed = collect($entries)->firstWhere('action', 'record.filed');

        $this->assertNotNull($filed);
        $this->assertArrayHasKey('properties', $filed);
        $this->assertArrayHasKey('document_reference', $filed['properties']);
    }

    public function test_a_link_entry_carries_its_reciprocal_id_in_properties(): void
    {
        $first = $this->record();
        $second = $this->record();

        $this->app->make(RecordLinkService::class)
            ->linkRecordToRecord($first, $second, LinkRelationship::Supports);

        $entries = $this->get(route('records.logs'))->viewData('drawerEntries');

        $linked = collect($entries)->firstWhere('action', 'record.linked');

        $this->assertNotNull($linked);
        $this->assertArrayHasKey('reciprocal_link_id', $linked['properties']);
    }

    public function test_it_never_shows_another_companys_entries(): void
    {
        $mine = $this->record();

        $second = $this->seedSecondCompany($this->company);
        $this->actingInCompany($second);
        $this->record();

        $this->actingInCompany($this->company);

        $response = $this->get(route('records.logs'));

        $response->assertOk();
        $response->assertDontSee($second->name);

        foreach ($response->viewData('entries') as $entry) {
            $this->assertSame((string) $this->company->id, (string) $entry->company_id);
        }
    }

    public function test_the_action_dropdown_excludes_another_companys_actions(): void
    {
        // This company produces nothing; the second company files a record.
        $second = $this->seedSecondCompany($this->company);
        $this->actingInCompany($second);
        $this->record();

        $this->actingInCompany($this->company);

        $this->assertCount(0, $this->get(route('records.logs'))->viewData('entries'));
        $this->assertCount(0, $this->get(route('records.logs'))->viewData('actions'));
    }

    public function test_the_csv_export_has_the_expected_columns(): void
    {
        $this->record();

        $response = $this->get(route('records.logs.export', ['format' => 'csv']));

        $response->assertOk();

        $csv = $response->streamedContent();
        $header = str($csv)->before("\r\n")->toString();

        foreach (['Log ID', 'Timestamp', 'Action', 'Description', 'Subject Type', 'Subject', 'User'] as $column) {
            $this->assertStringContainsString($column, $header);
        }
    }

    public function test_the_pdf_export_downloads_a_generated_document(): void
    {
        $this->record();

        $response = $this->get(route('records.logs.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_csv_export_resolves_both_subject_types(): void
    {
        $this->record();
        $this->createIntake();

        $csv = $this->get(route('records.logs.export', ['format' => 'csv']))->streamedContent();

        $this->assertStringContainsString('Intake Item', $csv);
        $this->assertStringContainsString('record.filed', $csv);
        $this->assertStringContainsString('intake.created', $csv);
    }

    public function test_the_csv_export_honours_the_action_filter(): void
    {
        $this->record();
        $this->createIntake();

        $csv = $this->get(route('records.logs.export', [
            'format' => 'csv',
            'action' => 'record.filed',
        ]))->streamedContent();

        $this->assertStringContainsString('record.filed', $csv);
        $this->assertStringNotContainsString('intake.created', $csv);
    }

    public function test_the_json_export_carries_a_compliance_envelope(): void
    {
        $this->record();

        $response = $this->get(route('records.logs.export', ['format' => 'json']));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame('ordo-records-audit-log', $payload['meta']['export']);
        $this->assertArrayHasKey('generated_at', $payload['meta']);
        $this->assertArrayHasKey('generated_by', $payload['meta']);
        $this->assertSame((string) $this->company->id, $payload['meta']['company_id']);
        $this->assertSame((string) $this->company->name, $payload['meta']['company_name']);
        $this->assertGreaterThan(0, $payload['meta']['entry_count']);
        $this->assertNotEmpty($payload['entries']);
    }

    /**
     * Shape of a single entry.
     *
     * The first entry is now the record of the export itself, because an export
     * is written before the transfer starts — see the controller. So the filed
     * record is looked up by action rather than by position.
     */
    public function test_the_json_export_includes_properties_and_the_log_id(): void
    {
        $this->record();

        $payload = $this->get(route('records.logs.export', ['format' => 'json']))->json();

        $entry = collect($payload['entries'])->firstWhere('action', 'record.filed');

        $this->assertNotNull($entry, 'The filed record did not survive into the export.');
        $this->assertArrayHasKey('log_id', $entry);
        $this->assertArrayHasKey('properties', $entry);
        $this->assertNotEmpty($entry['log_id']);
    }

    public function test_the_json_export_honours_the_filters(): void
    {
        $this->record();
        $this->createIntake();

        $payload = $this->get(route('records.logs.export', [
            'format' => 'json',
            'action' => 'record.filed',
        ]))->json();

        $this->assertSame(['action' => 'record.filed'], $payload['meta']['filters']);

        foreach ($payload['entries'] as $entry) {
            $this->assertSame('record.filed', $entry['action']);
        }
    }

    // ------------------------------------------------- the export is itself audited

    /**
     * A trail that cannot say it was read is a trail with a hole in it.
     *
     * Pulling the audit trail is the single most sensitive thing anyone can do in
     * ORDO, and it used to leave no trace. This is the entry that closes that.
     */
    public function test_exporting_the_trail_writes_an_entry_saying_so(): void
    {
        $this->record();

        $this->assertDatabaseMissing('record_activity_logs', [
            'company_id' => $this->company->id,
            'action' => 'audit.exported',
        ]);

        $this->get(route('records.logs.export', ['format' => 'json']))->assertOk();

        $this->assertDatabaseHas('record_activity_logs', [
            'company_id' => $this->company->id,
            'action' => 'audit.exported',
        ]);
    }

    /**
     * Both formats, because a CSV pulled by the same hand is the same event.
     */
    public function test_both_export_formats_are_recorded(): void
    {
        $this->record();

        $this->get(route('records.logs.export', ['format' => 'csv']))->assertOk();
        $this->get(route('records.logs.export', ['format' => 'json']))->assertOk();

        $this->assertSame(2, RecordActivityLog::query()
            ->where('action', 'audit.exported')
            ->where('company_id', $this->company->id)
            ->count());
    }

    public function test_the_export_entry_records_what_was_taken(): void
    {
        $this->record();

        $this->get(route('records.logs.export', ['format' => 'json', 'action' => 'record.filed']));

        $entry = RecordActivityLog::query()
            ->where('action', 'audit.exported')
            ->where('company_id', $this->company->id)
            ->firstOrFail();

        $this->assertSame('json', $entry->properties['format']);
        $this->assertGreaterThan(0, $entry->properties['entry_count']);
        $this->assertSame(['action' => 'record.filed'], $entry->properties['filters']);
        $this->assertStringContainsString('JSON', $entry->description);
    }

    /**
     * The count is the number of entries exported, which is decided before the
     * export is recorded — otherwise every export would report one more than it
     * contained.
     */
    public function test_the_entry_count_reflects_what_the_file_actually_holds(): void
    {
        $this->record();

        $payload = $this->get(route('records.logs.export', ['format' => 'json']))->json();

        $this->assertCount($payload['meta']['entry_count'], $payload['entries']);
    }

    // ------------------------------------------------------- bounded in memory

    /**
     * The trail grows without limit, and this export used to load all of it.
     *
     * Asserting the response is streamed rather than built proves the memory is
     * bounded by chunk size rather than by trail size, which is the only part of
     * that cannot be seen in the output.
     */
    public function test_the_json_export_is_streamed_rather_than_built_in_memory(): void
    {
        $this->record();

        $response = $this->get(route('records.logs.export', ['format' => 'json']));

        $this->assertInstanceOf(
            StreamedResponse::class,
            $response->baseResponse,
            'The JSON export was built in memory; a large trail would take the server down.',
        );
    }

    /**
     * Streaming the document by hand must still produce one valid JSON document,
     * including across the chunk boundary where the comma is written.
     */
    public function test_a_streamed_export_is_still_one_valid_document(): void
    {
        for ($i = 0; $i < 45; $i++) {
            $this->record();
        }

        $payload = json_decode($this->get(route('records.logs.export', ['format' => 'json']))->streamedContent(), true);

        $this->assertIsArray($payload, 'The streamed document did not parse.');
        $this->assertArrayHasKey('meta', $payload);
        $this->assertCount($payload['meta']['entry_count'], $payload['entries']);
    }

    // ------------------------------------------------------- the button's place

    public function test_the_header_has_a_unified_csv_and_pdf_export_dropdown_without_compliance_json(): void
    {
        $content = $this->get(route('records.logs'))->assertOk()->getContent() ?? '';

        $this->assertStringContainsString('>Export</summary>', $content);
        $this->assertStringContainsString('>Export CSV</a>', $content);
        $this->assertStringContainsString('>Export PDF</a>', $content);
        $this->assertStringNotContainsString('Compliance JSON', $content);
    }

    public function test_the_json_export_never_leaks_another_companys_entries(): void
    {
        $this->record();

        $second = $this->seedSecondCompany($this->company);
        $this->actingInCompany($second);
        $this->record();

        $this->actingInCompany($this->company);

        $payload = $this->get(route('records.logs.export', ['format' => 'json']))->json();

        foreach ($payload['entries'] as $entry) {
            $this->assertNotSame((string) $second->id, $entry['subject']);
        }
    }

    public function test_the_trail_rejects_updates_and_deletes(): void
    {
        $this->record();

        $entry = RecordActivityLog::query()->firstOrFail();

        try {
            $entry->update(['description' => 'Rewritten']);
            $this->fail('Updating an audit entry should have been refused.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Deleting an audit entry should have been refused.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_the_log_page_has_an_empty_state(): void
    {
        $this->get(route('records.logs'))
            ->assertOk()
            ->assertSee('No activity matches the selected filters.');
    }

    public function test_the_audit_log_subnav_item_is_present(): void
    {
        $this->get(route('records.logs'))
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee(route('records.logs'), false);
    }

    private function record(): Record
    {
        $company = $this->current();

        $classification = $this->classifications[$company->id]['301'] ??= $this->seedClassification($company);
        $index = $this->indexes[$company->id]['301'] ??= $this->seedIndex($company, $classification);

        $item = $this->createIntake();

        return $this->app->make(RecordFilingService::class)->file($item, [
            'record_classification_id' => $classification->id,
            'record_index_id' => $index->id,
        ]);
    }

    private function createIntake(): RecordIntakeItem
    {
        return $this->app->make(RecordIntakeService::class)->createIntakeItem(
            [$this->fakeImage('scan.jpg')],
            ['title' => 'General Information Sheet'],
        );
    }

    private function current(): Company
    {
        return $this->app->make(CompanyContext::class)->current();
    }

    private function seedClassification(Company $company): RecordClassification
    {
        $classification = new RecordClassification;
        $classification->forceFill([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code' => '301',
            'name' => 'Entity and Ownership',
            'is_active' => true,
        ])->save();

        return $classification;
    }

    private function seedIndex(Company $company, RecordClassification $classification): RecordIndex
    {
        $index = new RecordIndex;
        $index->forceFill([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'record_classification_id' => $classification->id,
            'code' => '301-6',
            'name' => 'General Information Sheet',
            'sort_order' => 0,
            'is_active' => true,
        ])->save();

        return $index;
    }
}
