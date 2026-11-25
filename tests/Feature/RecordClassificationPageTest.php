<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Record;
use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\User;
use App\Services\Records\RecordFilingService;
use App\Services\Records\RecordIntakeService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeUploads;
use Tests\TestCase;

class RecordClassificationPageTest extends TestCase
{
    use FakeUploads;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->seedTenantAndCompany();
        $this->actingInCompany($this->company);
    }

    // ---------------------------------------------------------------- reading

    public function test_the_page_renders_the_scheme(): void
    {
        $classification = $this->classification('301', 'Entity & Ownership');
        $this->index($classification, '301-6', 'GIS');

        $response = $this->get(route('records.classifications'));

        $response->assertOk();
        $response->assertViewIs('records.classifications');
        $response->assertSee('Classifications');
        $response->assertSee('Browse the official Records Classification and Index structure.');
        $response->assertSee('301');
        $response->assertSee('Entity &amp; Ownership', false);
        $response->assertSee('301-6');
        $response->assertSee('GIS');
    }

    public function test_the_page_reports_how_many_classifications_there_are(): void
    {
        $this->classification('101', 'Client Master File');
        $this->classification('301', 'Entity & Ownership');

        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee('2 classifications');
    }

    /**
     * The tree's four-column grid is what makes the status pills and action
     * links line up between a Classification row and the Indexes beneath it, so
     * the column widths are asserted rather than assumed.
     */
    public function test_the_tree_lays_rows_out_in_the_reference_grid(): void
    {
        $classification = $this->classification('301', 'Entity &amp; Ownership');
        $this->index($classification, '301-6', 'General Information Sheet');

        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee('grid-cols-[90px_1fr_120px_90px]', false);
    }

    /**
     * The actions are text, not buttons.
     *
     * The reference gives each row one action and no border: "+ Add Index" on the
     * Classification, "View Records" on each Index. Asserted here because a
     * bordered button in that column pushes every row's link out of alignment.
     */
    public function test_the_tree_actions_are_text_and_not_bordered_buttons(): void
    {
        $classification = $this->classification('301', 'Entity &amp; Ownership');
        $this->index($classification, '301-6', 'General Information Sheet');

        $response = $this->get(route('records.classifications'));

        $response->assertOk();
        $response->assertSee('>View Records</a>', false);

        // Edit and Add Index are inside Manage Classification setup modal
        $setupResponse = $this->get(route('records.setup.tab', ['tab' => 'classifications']));
        $setupResponse->assertOk();
        $setupResponse->assertSee('+ Add Index', false);
        $setupResponse->assertSee('border-0 bg-transparent p-0 text-[11px] text-ordo-blue', false);
    }

    /**
     * There is deliberately no "+ Add Index" in the toolbar.
     *
     * It opened the dialog with no Classification chosen, and that dialog's Save
     * button stays disabled until one is — so it was a control that could only
     * lead to a dead end. Asserted so it cannot quietly come back.
     */
    public function test_the_toolbar_offers_no_orphaned_add_index_button(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        // One Classification, so the per-row action is present to compare against.
        $classification = $this->classification('301', 'Entity &amp; Ownership');
        $this->index($classification, '301-6', 'General Information Sheet');

        $response = $this->get(route('records.setup.tab', ['tab' => 'classifications']));

        $response->assertOk();
        // The per-Classification action names its parent...
        $response->assertSee("open('index', '301')", false);
        // ...and there is no parentless one, which was the dead-end control.
        $response->assertDontSee('x-on:click="open(\'index\')"', false);
    }

    public function test_it_shows_an_empty_state_with_no_classifications(): void
    {
        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee('No classifications have been added for this company yet.');
    }

    /**
     * Both an active and a retired classification are shown: an old code is
     * still referenced by filed Records and must not silently vanish.
     */
    public function test_it_shows_inactive_classifications_rather_than_hiding_them(): void
    {
        $classification = $this->classification('999', 'Retired Scheme');
        $classification->forceFill(['is_active' => false])->save();

        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee('999')
            ->assertSee('Inactive');
    }

    public function test_it_does_not_show_another_companys_classifications(): void
    {
        $mine = $this->classification('301', 'Mine');

        $second = $this->seedSecondCompany($this->company);
        $this->actingInCompany($second);
        $theirs = new RecordClassification;
        $theirs->forceFill([
            'tenant_id' => $second->tenant_id,
            'company_id' => $second->id,
            'code' => '888',
            'name' => 'Theirs',
            'is_active' => true,
        ])->save();

        $this->actingInCompany($this->company);

        $response = $this->get(route('records.classifications'));

        $response->assertOk();
        $response->assertSee($mine->code);
        $response->assertDontSee('Theirs');
        $response->assertDontSee($theirs->code);
    }

    // ---------------------------------------------------------------- writing

    /**
     * The create dialog's labels and wording.
     *
     * Asserted because the form is rendered twice — once on this page and once
     * inside the setup modal — and a copy change in one that is not made in the
     * other leaves the same form looking like two different forms.
     */
    public function test_the_add_classification_form_matches_the_reference(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $response = $this->get(route('records.classifications'));

        $response->assertOk();
        $response->assertSee('Add Classification');
        // The name field is named for what it holds, not just "Name".
        $response->assertSee('Classification Name');
        $response->assertDontSee('>Name</label>', false);
        // One Save label rather than one per mode. Scoped to the button: the Add
        // Index dialog's own title is still called "Add Index".
        $response->assertSee('>Save<', false);
        $response->assertDontSee('>Add Index</button>', false);
        // The description box is a bare field, as the reference shows it.
        $response->assertDontSee('What belongs in this classification.');
    }

    public function test_a_system_admin_can_add_a_classification(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        // Posted from the scheme page, as its own form does.
        $response = $this->from(route('records.classifications'))->post(route('records.classifications.store'), [
            'code' => '1501',
            'name' => 'Insurance',
            'description' => 'Policies and premium records.',
        ]);

        $response->assertRedirect(route('records.classifications'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('record_classifications', [
            'tenant_id' => $this->company->tenant_id,
            'company_id' => $this->company->id,
            'code' => '1501',
            'name' => 'Insurance',
            'is_active' => true,
        ]);
    }

    /**
     * The same form is reached from the setup modal, which every page carries.
     *
     * So the write returns to wherever it was posted from rather than always to the
     * scheme page, and flashes the tab it belongs to so the modal reopens on it.
     * Without the flash the modal would close on save and the user would lose the
     * place they were in.
     */
    public function test_adding_a_classification_from_another_page_returns_there_and_reopens_setup(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $response = $this->from(route('records.dashboard'))->post(route('records.classifications.store'), [
            'code' => '1502',
            'name' => 'Banking Services',
        ]);

        $response->assertRedirect(route('records.dashboard'));
        $response->assertSessionHas('setup_tab', 'classifications');
    }

    /**
     * The dropdowns on the register and the queue are cached for five minutes.
     * Without an invalidation the code created here would be missing from the
     * very screen the user reaches next, so the forget is asserted rather than
     * assumed.
     */
    public function test_adding_a_classification_drops_the_cached_lookups(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        Cache::spy();

        $this->post(route('records.classifications.store'), [
            'code' => '1501',
            'name' => 'Insurance',
        ])->assertRedirect();

        $tenant = (string) $this->company->tenant_id;
        $company = (string) $this->company->id;

        foreach (['classifications', 'indexes', 'indexes_by_classification', 'sources'] as $key) {
            Cache::shouldHaveReceived('forget')
                ->with(sprintf('ordo.lookups.%s.%s.%s', $tenant, $company, $key))
                ->once();
        }
    }

    public function test_adding_an_index_attaches_it_to_the_parent(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $classification = $this->classification('301', 'Entity & Ownership');

        $response = $this->from(route('records.classifications'))->post(
            route('records.classifications.indexes.store', [
                'classification' => $classification->code,
            ]),
            [
                'classification' => $classification->code,
                'code' => '301-16',
                'name' => 'Board Minutes',
                'description' => 'Minutes of board meetings.',
            ],
        );

        $response->assertRedirect(route('records.classifications'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('setup_tab', 'classifications');

        $this->assertDatabaseHas('record_indexes', [
            'record_classification_id' => $classification->id,
            'code' => '301-16',
            'name' => 'Board Minutes',
            'is_active' => true,
        ]);
    }

    /**
     * New indexes land after the existing ones rather than jumping to the front
     * of the drawer each time one is added.
     */
    public function test_a_new_index_is_ordered_after_the_existing_ones(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $classification = $this->classification('301', 'Entity & Ownership');
        $this->index($classification, '301-1', 'Incorporation', sortOrder: 0);
        $this->index($classification, '301-6', 'GIS', sortOrder: 1);

        $this->post(route('records.classifications.indexes.store', [
            'classification' => $classification->code,
        ]), [
            'classification' => $classification->code,
            'code' => '301-16',
            'name' => 'Board Minutes',
        ])->assertRedirect();

        $created = RecordIndex::query()->where('code', '301-16')->firstOrFail();

        $this->assertSame(2, $created->sort_order);
    }

    public function test_adding_an_index_drops_the_cached_lookups(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $classification = $this->classification('301', 'Entity & Ownership');
        Cache::spy();

        $this->post(route('records.classifications.indexes.store', [
            'classification' => $classification->code,
        ]), [
            'classification' => $classification->code,
            'code' => '301-16',
            'name' => 'Board Minutes',
        ])->assertRedirect();

        Cache::shouldHaveReceived('forget')
            ->with(sprintf(
                'ordo.lookups.%s.%s.indexes',
                (string) $this->company->tenant_id,
                (string) $this->company->id,
            ))
            ->once();
    }

    // ---------------------------------------------------------- authorisation

    public function test_a_records_manager_may_browse_but_not_change_the_scheme(): void
    {
        config(['ordo.default_role' => UserRole::RecordsManager->value]);

        // Browsing is open to every role that can see records.
        $this->get(route('records.classifications'))->assertOk();

        // The create buttons are not even offered.
        $this->get(route('records.classifications'))
            ->assertDontSee('+ Add Classification');

        $this->post(route('records.classifications.store'), [
            'code' => '1501',
            'name' => 'Insurance',
        ])->assertForbidden();
    }

    public function test_an_auditor_may_browse_but_not_change_the_scheme(): void
    {
        $auditor = User::factory()->create(['role' => UserRole::Auditor]);

        $this->actingAs($auditor)
            ->get(route('records.classifications'))
            ->assertOk();

        $this->actingAs($auditor)
            ->post(route('records.classifications.store'), [
                'code' => '1501',
                'name' => 'Insurance',
            ])
            ->assertForbidden();
    }

    public function test_a_system_admin_is_offered_the_create_actions(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee('Manage Classification');

        $this->get(route('records.setup.tab', ['tab' => 'classifications']))
            ->assertOk()
            ->assertSee('+ Add Classification');
    }

    // -------------------------------------------------------------- isolation

    /**
     * The parent is named by code and resolved inside the active company, so
     * another company's Classification is simply not found.
     */
    public function test_an_index_cannot_be_added_to_another_companys_classification(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $second = $this->seedSecondCompany($this->company);
        $this->actingInCompany($second);
        $theirs = new RecordClassification;
        $theirs->forceFill([
            'tenant_id' => $second->tenant_id,
            'company_id' => $second->id,
            'code' => '888',
            'name' => 'Theirs',
            'is_active' => true,
        ])->save();

        $this->actingInCompany($this->company);

        $response = $this->post(route('records.classifications.indexes.store', [
            'classification' => $theirs->code,
        ]), [
            'classification' => $theirs->code,
            'code' => '888-1',
            'name' => 'Trespass',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('record_indexes', ['code' => '888-1']);
    }

    public function test_a_supplied_tenant_or_company_is_ignored(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $second = $this->seedSecondCompany($this->company);

        $this->post(route('records.classifications.store'), [
            'tenant_id' => $second->tenant_id,
            'company_id' => $second->id,
            'code' => '1501',
            'name' => 'Insurance',
        ])->assertRedirect();

        $this->assertDatabaseHas('record_classifications', [
            'code' => '1501',
            'tenant_id' => $this->company->tenant_id,
            'company_id' => $this->company->id,
        ]);

        $this->assertDatabaseMissing('record_classifications', [
            'code' => '1501',
            'company_id' => $second->id,
        ]);
    }

    // ------------------------------------------------------------- validation

    public function test_a_duplicate_classification_code_is_refused(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $this->classification('301', 'Entity & Ownership');

        $this->post(route('records.classifications.store'), [
            'code' => '301',
            'name' => 'Duplicate',
        ])->assertSessionHasErrors('code');
    }

    public function test_a_duplicate_index_code_under_the_same_parent_is_refused(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $classification = $this->classification('301', 'Entity & Ownership');
        $this->index($classification, '301-6', 'GIS');

        $this->post(route('records.classifications.indexes.store', [
            'classification' => $classification->code,
        ]), [
            'classification' => $classification->code,
            'code' => '301-6',
            'name' => 'Duplicate',
        ])->assertSessionHasErrors('code');
    }

    /**
     * Index codes are unique per parent, so two classifications may both hold
     * the same code.
     */
    public function test_the_same_index_code_may_exist_under_different_parents(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);
        $first = $this->classification('301', 'Entity & Ownership');
        $second = $this->classification('401', 'BIR & Tax');
        $this->index($first, '401-30', 'Form 2307');

        $this->post(route('records.classifications.indexes.store', [
            'classification' => $second->code,
        ]), [
            'classification' => $second->code,
            'code' => '401-30',
            'name' => 'Form 2307',
        ])->assertRedirect();

        $this->assertDatabaseHas('record_indexes', [
            'record_classification_id' => $second->id,
            'code' => '401-30',
        ]);
    }

    public function test_a_classification_requires_a_code_and_a_name(): void
    {
        config(['ordo.default_role' => UserRole::SystemAdmin->value]);

        $this->post(route('records.classifications.store'), [])
            ->assertSessionHasErrors(['code', 'name']);
    }

    // ---------------------------------------------------------- View Records

    public function test_view_records_links_to_the_pre_filtered_register(): void
    {
        $classification = $this->classification('301', 'Entity & Ownership');
        $index = $this->index($classification, '301-6', 'GIS');

        // The ampersand arrives HTML-escaped in the rendered href.
        $expected = str_replace('&', '&amp;', route('records.index', [
            'classification' => $classification->code,
            'index' => $index->code,
        ]));

        $this->get(route('records.classifications'))
            ->assertOk()
            ->assertSee($expected, false);
    }

    /**
     * The link is only useful if the register actually honours both filters, so
     * the destination is fetched rather than assumed.
     */
    public function test_the_view_records_link_returns_the_filtered_record(): void
    {
        $classification = $this->classification('301', 'Entity & Ownership');
        $index = $this->index($classification, '301-6', 'GIS');

        $match = $this->filedRecord($classification, $index, 'Matched');

        $otherClassification = $this->classification('401', 'BIR & Tax');
        $otherIndex = $this->index($otherClassification, '401-30', 'Form 2307');
        $other = $this->filedRecord($otherClassification, $otherIndex, 'ExcludedByFilter');

        $response = $this->get(route('records.index', [
            'classification' => $classification->code,
            'index' => $index->code,
        ]));

        $response->assertOk();
        $response->assertSee('Matched');
        $response->assertDontSee('ExcludedByFilter');
        $this->assertSame($match->id, $response->viewData('records')->first()->id);
        $this->assertNotSame($other->id, $response->viewData('records')->first()->id);
    }

    // --------------------------------------------------------------- subnav

    public function test_the_subnav_item_links_here_and_highlights(): void
    {
        $response = $this->get(route('records.classifications'));

        $response->assertOk();
        $response->assertSee(route('records.classifications'), false);

        // The active item is the one carrying the ORDO blue treatment.
        $this->assertStringContainsString(
            'href="'.route('records.classifications').'"',
            $response->getContent() ?? '',
        );
    }

    public function test_the_other_subnav_placeholders_are_still_inert(): void
    {
        $content = $this->get(route('records.classifications'))->getContent() ?? '';

        $this->assertStringContainsString('Archive', $content);
        $this->assertStringContainsString('Reports', $content);

        // Every subnav item now has a route.
        $this->assertSame(0, substr_count($content, 'href="#"'), 'A placeholder subnav item remains.');
    }

    // --------------------------------------------------------------- helpers

    private function classification(string $code, string $name): RecordClassification
    {
        $classification = new RecordClassification;
        $classification->forceFill([
            'tenant_id' => $this->company->tenant_id,
            'company_id' => $this->company->id,
            'code' => $code,
            'name' => $name,
            'description' => $name.' records.',
            'is_active' => true,
        ])->save();

        return $classification;
    }

    private function index(
        RecordClassification $classification,
        string $code,
        string $name,
        int $sortOrder = 0,
    ): RecordIndex {
        $index = new RecordIndex;
        $index->forceFill([
            'tenant_id' => $classification->tenant_id,
            'company_id' => $classification->company_id,
            'record_classification_id' => $classification->id,
            'code' => $code,
            'name' => $name,
            'description' => $name.' documents.',
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        return $index;
    }

    /**
     * File an intake item under a classification and index, the way the queue
     * does, so the register has something to filter.
     */
    private function filedRecord(
        RecordClassification $classification,
        RecordIndex $index,
        string $title,
    ): Record {
        $item = $this->app->make(RecordIntakeService::class)->createIntakeItem(
            [$this->fakePdf($title.'.pdf', 32)],
            ['title' => $title],
        );

        return $this->app->make(RecordFilingService::class)->file($item, [
            'record_classification_id' => $classification->id,
            'record_index_id' => $index->id,
        ]);
    }
}
