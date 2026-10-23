<?php

namespace Tests\Feature;

use App\Models\ApprovalAuthorityCode;
use App\Models\Transmittal;
use App\Models\TransmittalReceipt;
use App\Models\TransmittalUpload;
use App\Models\User;
use App\Services\ResumableTransmittalUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TransmittalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_register_needs_no_login_and_there_is_no_signup_or_user_creation_page(): void
    {
        $this->get(route('transmittals.index'))
            ->assertOk()
            ->assertSee('Transmittal V2')
            ->assertSee('Smart Upload')
            ->assertSee('Pending Approval')
            ->assertDontSee('Deleted');
        $this->get(route('transmittals.create'))
            ->assertRedirect(route('transmittals.index', ['add' => 1]));
        $this->get(route('transmittals.index', ['add' => 1]))
            ->assertOk()
            ->assertSee('Add Transmittal')
            ->assertSee('Save Draft')
            ->assertSee('SEND')
            ->assertSee('RECEIVE')
            ->assertSee('data-document-preview-pane', false)
            ->assertSee('Confidentiality Notice')
            ->assertSee('List of Items');
        $this->get(route('login'))->assertOk()->assertSee('Administrator sign in')->assertDontSee('Create account');
        $this->get('/login')->assertNotFound();
        $this->post('/register-first-administrator')->assertNotFound();
        $this->get('/team')->assertNotFound();
    }

    public function test_browser_lifecycle_test_commands_are_disabled_outside_local_sqlite(): void
    {
        $environment = app()->environment();
        $databaseConnection = config('database.default');
        $this->app->instance('env', 'production');

        $this->artisan('ordo:browser-test:setup')
            ->expectsOutputToContain('only be created in local/testing environments using SQLite')
            ->assertExitCode(1);
        $this->artisan('ordo:browser-test:cleanup', ['tag' => 'abcdefgh'])
            ->expectsOutputToContain('only be cleaned in local/testing environments using SQLite')
            ->assertExitCode(1);

        $this->app->instance('env', 'local');
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
        ]);

        $this->artisan('ordo:browser-test:setup')
            ->expectsOutputToContain('only be created in local/testing environments using SQLite')
            ->assertExitCode(1);
        $this->artisan('ordo:browser-test:cleanup', ['tag' => 'abcdefgh'])
            ->expectsOutputToContain('only be cleaned in local/testing environments using SQLite')
            ->assertExitCode(1);

        config(['database.default' => $databaseConnection]);
        $this->app->instance('env', $environment);
    }

    public function test_local_browser_lifecycle_fixtures_can_be_created_and_cleaned_by_tag(): void
    {
        Storage::fake('local');

        $this->artisan('ordo:browser-test:setup')
            ->expectsQuestion('Test administrator password (12 characters minimum)', 'BrowserLifecycleTest-Password-42')
            ->expectsQuestion('Confirm test administrator password', 'BrowserLifecycleTest-Password-42')
            ->expectsOutputToContain('Tracking numbers:')
            ->assertExitCode(0);

        $restoreRecord = Transmittal::query()
            ->where('tracking_number', 'like', 'BROWSER-TEST-%-RESTORE')
            ->firstOrFail();
        $this->assertSame(1, preg_match('/^BROWSER-TEST-([a-z0-9]{8})-RESTORE$/', $restoreRecord->tracking_number, $matches));
        $tag = $matches[1];
        $admin = User::query()->where('email', "browser-admin+{$tag}@example.test")->firstOrFail();
        $protectedRecord = Transmittal::query()
            ->where('tracking_number', "BROWSER-TEST-{$tag}-PROTECTED")
            ->firstOrFail();

        $this->assertTrue($admin->canApproveTransmittals());
        $this->assertSame(3, Transmittal::query()->where('tracking_number', 'like', "BROWSER-TEST-{$tag}-%")->count());
        $this->assertSame(1, $protectedRecord->proofs()->count());
        $this->assertSame(1, $protectedRecord->receipts()->count());
        $this->assertSame(1, $protectedRecord->acknowledgments()->count());
        $this->assertSame(3, Transmittal::query()
            ->where('prepared_by', $admin->id)
            ->where('tracking_number', 'like', "BROWSER-TEST-{$tag}-%")
            ->count());
        $this->actingAs($admin)
            ->delete(route('transmittals.destroy', $protectedRecord), ['reason' => 'Exercise protected fixture.'])
            ->assertRedirect(route('transmittals.index'));
        $this->delete(route('transmittals.force-delete', $protectedRecord->id), [
            'reason' => 'Must retain fixture evidence.',
            'confirm_tracking_number' => $protectedRecord->tracking_number,
        ])->assertStatus(409);
        $this->assertSoftDeleted('transmittals', ['id' => $protectedRecord->id]);
        $this->assertSame(1, $protectedRecord->fresh()->proofs()->count());
        $this->assertSame(1, $protectedRecord->fresh()->receipts()->count());
        $this->assertSame(1, $protectedRecord->fresh()->acknowledgments()->count());

        $this->artisan('ordo:browser-test:cleanup', ['tag' => $tag])->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
        $this->assertSame(0, Transmittal::withTrashed()->where('tracking_number', 'like', "BROWSER-TEST-{$tag}-%")->count());
        $this->assertSame(0, DB::table('transmittal_lifecycle_archives')->where('tracking_number', 'like', "BROWSER-TEST-{$tag}-%")->count());
    }

    public function test_public_submission_lists_transmittal_but_private_attachments_require_authorized_access(): void
    {
        Storage::fake('local');

        $this->post(route('transmittals.store'), [
            'intent' => 'submit',
            'subject' => 'Issued drawing set',
            'purpose' => 'For review and comment.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'John Kelly & Company',
            'recipient_address' => '100 Main Street',
            'related_company' => 'Example Co',
            'delivery_method' => 'electronic',
            'confidentiality' => 'confidential',
            'recipients' => [
                ['name' => 'Alex Recipient', 'email' => 'alex@example.test', 'organization' => 'Example Co'],
            ],
            'categories' => ['document'],
            'ocr_tokens' => [''],
            'documents' => [UploadedFile::fake()->create('drawing.pdf', 30, 'application/pdf')],
        ])->assertRedirect();

        $transmittal = Transmittal::query()->firstOrFail();
        $document = $transmittal->documents()->firstOrFail();

        $this->assertSame('pending_approval', $transmittal->status);
        $this->assertNull($transmittal->prepared_by);
        $this->assertNull($document->uploaded_by);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->get(route('transmittals.index'))->assertOk()->assertSee($transmittal->tracking_number);
        $this->get(route('transmittals.show', $transmittal))->assertOk()->assertSee('Public submitter');
        $this->get(route('transmittals.documents.download', [$transmittal, $document]))->assertNotFound();

        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->get(route('transmittals.documents.download', [$transmittal, $document]))
            ->assertOk();
        Storage::disk('local')->put($document->storage_path, 'tampered content');
        $this->get(route('transmittals.documents.download', [$transmittal, $document]))->assertStatus(409);
    }

    public function test_public_register_filters_and_searches_transmittals_using_the_visible_tabs(): void
    {
        $submitted = $this->pendingTransmittal('TRN-00160');
        $submitted->forceFill([
            'direction' => 'receive',
            'transmittal_date' => '2026-10-13',
            'origin_office' => 'Client Office',
            'related_company' => 'ABC Corporation',
        ])->save();
        $submitted->recipients()->firstOrFail()->update([
            'name' => 'Jordan Recipient',
            'email' => 'jordan@example.test',
        ]);
        $rejected = $this->pendingTransmittal('TRN-00159');
        $rejected->forceFill(['status' => 'rejected'])->save();

        $this->get(route('transmittals.index', ['filter' => 'submitted']))
            ->assertOk()
            ->assertSee('TRN-00160')
            ->assertDontSee('TRN-00159');
        $this->get(route('transmittals.index', ['filter' => 'reverted']))
            ->assertOk()
            ->assertSee('TRN-00159')
            ->assertDontSee('TRN-00160');
        $this->get(route('transmittals.index', ['q' => 'jordan@example.test']))
            ->assertOk()
            ->assertSee('TRN-00160')
            ->assertDontSee('TRN-00159');
        $this->get(route('transmittals.index', ['filter' => 'deleted']))
            ->assertNotFound();
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->get(route('transmittals.index', ['filter' => 'deleted']))
            ->assertOk()
            ->assertSee('Deleted')
            ->assertSee('No records found.');
    }

    public function test_public_draft_can_only_be_resumed_in_its_browser_session_and_submitted_for_review(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Draft package',
            'purpose' => 'Pending completion.',
            'direction' => 'receive',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'Client Office',
            'recipient_address' => '10 Sample Road',
            'related_company' => 'Sample Client',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => 'Draft recipient', 'email' => '', 'organization' => '']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));

        $draft = Transmittal::query()->firstOrFail();
        $this->assertSame('TRN-00001', $draft->tracking_number);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('receive', $draft->direction);
        $this->assertSame('2026-10-14', $draft->transmittal_date->toDateString());
        $this->assertNotEmpty($draft->edit_token_hash);
        $this->assertSame(0, $draft->recipients()->count());

        $this->get(route('transmittals.edit', $draft))
            ->assertRedirect(route('transmittals.index', ['edit' => $draft->id]));
        $this->get(route('transmittals.index', ['edit' => $draft->id]))
            ->assertOk()
            ->assertSee('Edit Draft')
            ->assertSee('Submit for review');

        $this->put(route('transmittals.update', $draft), [
            'intent' => 'submit',
            'subject' => 'Draft package',
            'purpose' => 'Ready for review.',
            'direction' => 'receive',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'Client Office',
            'recipient_address' => '10 Sample Road',
            'related_company' => 'Sample Client',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => 'Final recipient', 'email' => 'final@example.test', 'organization' => 'Sample Client']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'submitted']));

        $this->assertDatabaseHas('transmittals', [
            'id' => $draft->id,
            'status' => 'pending_approval',
        ]);
        $this->assertNotEmpty($draft->fresh()->edit_token_hash);
        $this->assertDatabaseHas('transmittal_recipients', [
            'transmittal_id' => $draft->id,
            'email' => 'final@example.test',
        ]);
        $this->get(route('transmittals.edit', $draft))->assertNotFound();
    }

    public function test_smart_upload_creates_a_session_protected_draft_and_workspace_saves_items_and_signatories(): void
    {
        Storage::fake('local');
        $uploadedFile = UploadedFile::fake()->create('drawing-set.pdf', 20, 'application/pdf');
        $ocrPath = $uploadedFile->getRealPath();
        $ocrToken = Crypt::encryptString(json_encode([
            'name' => 'drawing-set.pdf',
            'sha256' => hash_file('sha256', $ocrPath),
            'category' => 'document',
            'text' => 'Reference: DWG-482',
            'fields' => [
                'reference' => 'DWG-482',
                'unique_id' => 'PLAN-009',
                'subject' => 'Scanned plan cover',
                'description' => 'Cover drawing set',
                'company' => 'Example Co',
                'recipient_name' => 'Alex Recipient',
                'recipient_email' => 'alex@example.test',
                'recipient_address' => '100 Main Street',
                'date' => '2026-10-14',
            ],
            'field_metadata' => [
                'company' => ['source' => 'tesseract_ocr', 'confidence' => 'medium'],
            ],
        ], JSON_THROW_ON_ERROR));

        $response = $this->post(route('transmittals.smart-upload.store'), [
            'subject' => 'Scanned drawing package',
            'external_reference' => 'DWG-482',
            'related_company' => 'Example Co',
            'recipient_address' => '100 Main Street',
            'recipient_name' => 'Alex Recipient',
            'recipient_email' => 'alex@example.test',
            'categories' => ['document'],
            'ocr_tokens' => [$ocrToken],
            'documents' => [$uploadedFile],
        ]);

        $transmittal = Transmittal::query()->firstOrFail();
        $response->assertRedirect(route('transmittals.workspace', $transmittal));
        $this->assertSame('draft', $transmittal->status);
        $this->assertSame('Scanned drawing package', $transmittal->subject);
        $this->assertSame('DWG-482', $transmittal->external_reference);
        $this->assertSame('Public submitter', $transmittal->prepared_by_name);
        $this->assertSame('Example Co', $transmittal->related_company);
        $this->assertSame('100 Main Street', $transmittal->recipient_address);
        $this->assertDatabaseHas('transmittal_recipients', [
            'transmittal_id' => $transmittal->id,
            'email' => 'alex@example.test',
        ]);
        $this->assertDatabaseHas('transmittal_documents', [
            'transmittal_id' => $transmittal->id,
            'original_name' => 'drawing-set.pdf',
            'category' => 'document',
        ]);

        $this->get(route('transmittals.workspace', $transmittal))
            ->assertOk()
            ->assertSee('DOCUMENT PREVIEW')
            ->assertSee('List of Items')
            ->assertSee('Prepared by')
            ->assertSee('drawing-set.pdf');
        $uploadedDocument = $transmittal->documents()->firstOrFail();
        $this->get(route('transmittals.documents.download', [$transmittal, $uploadedDocument]))
            ->assertOk();
        $this->assertSame(1, $transmittal->items()->count());
        $this->assertSame('drawing-set', $transmittal->items()->firstOrFail()->particular);
        $this->assertSame('PLAN-009', $transmittal->items()->firstOrFail()->unique_id);
        $this->assertSame('Cover drawing set', $transmittal->items()->firstOrFail()->description);
        $this->assertSame('tesseract_ocr', $uploadedDocument->extracted_data['_field_metadata']['company']['source']);

        $this->put(route('transmittals.update', $transmittal), [
            'intent' => 'draft',
            'subject' => 'Revised drawing package',
            'external_reference' => 'DWG-483',
            'purpose' => 'Return the signed drawing package.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'John Kelly & Company',
            'recipient_address' => '200 Oak Avenue',
            'related_company' => 'Updated Example Co',
            'delivery_method' => 'electronic',
            'delivery_type' => 'courier',
            'delivery_actions' => ['delivery', 'email'],
            'operations_manager' => 'Morgan Manager',
            'delivered_by' => 'Courier One',
            'received_by' => 'Taylor Receiver',
            'processing_company' => 'Example Co',
            'confidentiality' => 'standard',
            'recipients' => [[
                'name' => 'Jordan Recipient',
                'email' => 'jordan@example.test',
                'organization' => 'Updated Example Co',
            ]],
            'items' => [[
                'particular' => 'Signed plan set',
                'unique_id' => 'PLAN-009',
                'quantity' => '3 sets',
                'description' => 'Approved construction plans',
                'remarks' => 'Return one stamped copy.',
                'attachment' => UploadedFile::fake()->create('signed-plan.pdf', 12, 'application/pdf'),
            ]],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));

        $transmittal->refresh();
        $item = $transmittal->items()->firstOrFail();
        $this->assertSame('draft', $transmittal->status);
        $this->assertSame('Revised drawing package', $transmittal->subject);
        $this->assertSame('DWG-483', $transmittal->external_reference);
        $this->assertSame('Return the signed drawing package.', $transmittal->purpose);
        $this->assertSame('200 Oak Avenue', $transmittal->recipient_address);
        $this->assertSame('Updated Example Co', $transmittal->related_company);
        $this->assertSame('courier', $transmittal->delivery_type);
        $this->assertSame(['delivery', 'email'], $transmittal->delivery_actions);
        $this->assertSame('Public submitter', $transmittal->prepared_by_name);
        $this->assertSame('Example Co', $transmittal->processing_company);
        $this->assertDatabaseHas('transmittal_recipients', [
            'transmittal_id' => $transmittal->id,
            'name' => 'Jordan Recipient',
            'email' => 'jordan@example.test',
            'organization' => 'Updated Example Co',
        ]);
        $this->assertSame('PLAN-009', $item->unique_id);
        $this->assertNotNull($item->document_id);
        Storage::disk('local')->assertExists($item->document->storage_path);
        $this->assertDatabaseHas('transmittal_documents', [
            'transmittal_id' => $transmittal->id,
            'original_name' => 'drawing-set.pdf',
        ]);

        $workspace = $this->get(route('transmittals.workspace', $transmittal))
            ->assertOk()
            ->assertSee('Revised drawing package')
            ->assertSee('value="DWG-483"', false)
            ->assertSee('Return the signed drawing package.')
            ->assertSee('200 Oak Avenue')
            ->assertSee('value="Updated Example Co"', false)
            ->assertSee('jordan@example.test')
            ->assertSee('Signed plan set')
            ->assertSee('drawing-set.pdf')
            ->assertSee('signed-plan.pdf');
        $workspace->assertDontSee('Some details need attention.');

        $this->withSession(['transmittal_draft_tokens' => []])
            ->get(route('transmittals.workspace', $transmittal))
            ->assertNotFound();
    }

    public function test_missing_workspace_intent_preserves_old_input_and_does_not_change_draft_status(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Original workspace draft',
            'purpose' => 'Original purpose remains saved.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));
        $transmittal = Transmittal::query()->firstOrFail();

        $this->from(route('transmittals.workspace', $transmittal))
            ->followingRedirects()
            ->put(route('transmittals.update', $transmittal), [
            'subject' => 'User-entered replacement subject',
            'purpose' => 'User-entered replacement purpose.',
            'direction' => 'receive',
            'transmittal_date' => '2026-10-15',
            'origin_office' => 'Client Office',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])
            ->assertOk()
            ->assertSee('The intent field is required. Choose Save Draft or Submit for review.')
            ->assertSee('value="User-entered replacement subject"', false)
            ->assertSee('User-entered replacement purpose.');

        $this->assertSame('draft', $transmittal->fresh()->status);
        $this->assertSame('Original workspace draft', $transmittal->fresh()->subject);
        $this->assertSame('Original purpose remains saved.', $transmittal->fresh()->purpose);
        $this->assertDatabaseMissing('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'submitted',
        ]);
    }

    public function test_submit_for_review_with_missing_recipient_keeps_workspace_draft_editable(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Needs recipient',
            'purpose' => 'Complete recipient details before review.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));
        $transmittal = Transmittal::query()->firstOrFail();

        $this->put(route('transmittals.update', $transmittal), [
            'intent' => 'submit',
            'subject' => 'Needs recipient',
            'purpose' => 'Complete recipient details before review.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])->assertSessionHasErrors('recipients');

        $this->assertSame('draft', $transmittal->fresh()->status);
        $this->get(route('transmittals.workspace', $transmittal))->assertOk();
    }

    public function test_workspace_does_not_replace_saved_optional_fields_when_empty_values_are_submitted(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Retain existing values',
            'external_reference' => 'REF-OLD',
            'purpose' => 'Persist the original purpose.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'recipient_address' => '10 Existing Road',
            'related_company' => 'Existing Client',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [[
                'name' => 'Existing Recipient',
                'email' => 'existing@example.test',
                'organization' => 'Existing Client',
            ]],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));
        $transmittal = Transmittal::query()->firstOrFail();

        $this->put(route('transmittals.update', $transmittal), [
            'intent' => 'draft',
            'subject' => 'Retain existing values',
            'external_reference' => '',
            'purpose' => 'Persist the original purpose.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'recipient_address' => '',
            'related_company' => '',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => '', 'email' => '', 'organization' => '']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));

        $this->assertSame('REF-OLD', $transmittal->fresh()->external_reference);
        $this->assertSame('10 Existing Road', $transmittal->fresh()->recipient_address);
        $this->assertSame('Existing Client', $transmittal->fresh()->related_company);
        $this->assertDatabaseHas('transmittal_recipients', [
            'transmittal_id' => $transmittal->id,
            'email' => 'existing@example.test',
        ]);
    }

    public function test_workspace_update_is_not_found_without_the_draft_owner_session_or_admin(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Private draft',
            'purpose' => 'Owned by another browser session.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));
        $transmittal = Transmittal::query()->firstOrFail();
        $this->withSession(['transmittal_draft_tokens' => []])
            ->put(route('transmittals.update', $transmittal), [
                'intent' => 'draft',
                'subject' => 'Unauthorized change',
                'purpose' => 'This must not be saved.',
                'direction' => 'receive',
                'transmittal_date' => '2026-10-15',
                'origin_office' => 'Other Office',
                'delivery_method' => 'electronic',
                'confidentiality' => 'standard',
            ])->assertNotFound();

        $this->assertSame('Private draft', $transmittal->fresh()->subject);
        $this->assertSame('Owned by another browser session.', $transmittal->fresh()->purpose);
        $this->assertSame('draft', $transmittal->fresh()->status);
    }

    public function test_reverted_transmittal_can_be_edited_and_resubmitted_under_the_same_tracking_number(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'draft',
            'subject' => 'Initial package',
            'purpose' => 'Initial purpose.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
        ])->assertRedirect(route('transmittals.index', ['filter' => 'uploaded']));

        $transmittal = Transmittal::query()->firstOrFail();
        $this->put(route('transmittals.update', $transmittal), [
            'intent' => 'submit',
            'subject' => 'Initial package',
            'purpose' => 'Initial purpose.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => 'Receiver', 'email' => 'receiver@example.test']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'submitted']));

        $trackingNumber = $transmittal->fresh()->tracking_number;
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->post(route('transmittals.reject', $transmittal), ['rejection_reason' => 'Please include the correct document reference.'])
            ->assertRedirect(route('transmittals.show', $transmittal));

        $this->get(route('transmittals.workspace', $transmittal))
            ->assertOk()
            ->assertSee('Transmittal Workspace');

        $this->put(route('transmittals.update', $transmittal), [
            'intent' => 'submit',
            'subject' => 'Revised package',
            'external_reference' => 'REF-200',
            'purpose' => 'Corrected and ready for review.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => 'Receiver', 'email' => 'receiver@example.test']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'submitted']));

        $transmittal->refresh();
        $this->assertSame($trackingNumber, $transmittal->tracking_number);
        $this->assertSame('pending_approval', $transmittal->status);
        $this->assertSame('Revised package', $transmittal->subject);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'reverted_for_revision',
        ]);
    }

    public function test_session_owner_can_recall_pending_submission_and_reason_is_audited(): void
    {
        $this->post(route('transmittals.store'), [
            'intent' => 'submit',
            'subject' => 'Recall candidate',
            'purpose' => 'Review this before it is sent.',
            'direction' => 'send',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'ORDO',
            'delivery_method' => 'electronic',
            'confidentiality' => 'standard',
            'recipients' => [['name' => 'Recipient', 'email' => 'recall@example.test']],
        ])->assertRedirect(route('transmittals.index', ['filter' => 'submitted']));

        $transmittal = Transmittal::query()->firstOrFail();
        $this->post(route('transmittals.recall', $transmittal), ['reason' => 'Correct the recipient before review.'])
            ->assertRedirect(route('transmittals.workspace', $transmittal));

        $this->assertSame('draft', $transmittal->fresh()->status);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'recalled',
            'description' => 'Submission recalled from administrator review.',
        ]);
        $this->assertSame(
            'Correct the recipient before review.',
            $transmittal->histories()->where('action', 'recalled')->firstOrFail()->metadata['reason']
        );
    }

    public function test_recall_and_cancel_require_owner_or_admin_and_a_valid_transition_reason(): void
    {
        $transmittal = $this->pendingTransmittal('ORDO-2026-LIFECYCLE01');

        $this->post(route('transmittals.recall', $transmittal), ['reason' => 'Should not be authorized'])
            ->assertNotFound();
        $this->withSession(['transmittal_draft_tokens' => [
            $transmittal->id => 'incorrect-session-token',
        ]])->post(route('transmittals.cancel', $transmittal), ['reason' => 'Unauthorized'])
            ->assertNotFound();
        $this->actingAs(User::factory()->create(['transmittal_role' => 'administrator']))
            ->post(route('transmittals.recall', $transmittal), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs(User::factory()->create(['transmittal_role' => 'administrator']))
            ->post(route('transmittals.cancel', $transmittal), ['reason' => 'Cancelled at request of submitter.'])
            ->assertRedirect(route('transmittals.show', $transmittal));

        $this->assertSame('cancelled', $transmittal->fresh()->status);
        $this->assertSame('pending_approval', $transmittal->fresh()->cancelled_from_status);
        $this->assertSame('Cancelled at request of submitter.', $transmittal->fresh()->cancellation_reason);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'cancelled',
        ]);
    }

    public function test_soft_delete_restore_and_permanent_delete_preserve_lifecycle_audit_archive(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $transmittal = $this->pendingTransmittal('ORDO-2026-LIFECYCLE02');
        $contents = 'draft attachment';
        $path = "transmittals/{$transmittal->id}/draft.pdf";
        Storage::disk('local')->put($path, $contents);
        $transmittal->documents()->create([
            'original_name' => 'draft.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);

        $this->actingAs(User::factory()->create(['transmittal_role' => 'staff']))
            ->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Unauthorized deletion.'])
            ->assertForbidden();
        $this->assertNotSoftDeleted('transmittals', ['id' => $transmittal->id]);

        $this->actingAs($administrator)
            ->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Duplicate draft record.'])
            ->assertRedirect(route('transmittals.index'))
            ->assertSessionHas('status', 'Transmittal moved to Deleted. Its proof and audit history remain intact.');

        $this->assertSoftDeleted('transmittals', ['id' => $transmittal->id]);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'soft_deleted',
        ]);
        Storage::disk('local')->assertExists($path);
        $this->get(route('transmittals.index'))
            ->assertOk()
            ->assertDontSee($transmittal->tracking_number);
        $this->get(route('transmittals.index', ['filter' => 'deleted']))
            ->assertOk()
            ->assertSee($transmittal->tracking_number);
        $this->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Already deleted.'])
            ->assertNotFound();

        $this->actingAs(User::factory()->create(['transmittal_role' => 'staff']))
            ->post(route('transmittals.restore', $transmittal->id))
            ->assertForbidden();
        $this->assertSoftDeleted('transmittals', ['id' => $transmittal->id]);
        $this->actingAs($administrator)
            ->post(route('transmittals.restore', $transmittal->id))
            ->assertRedirect(route('transmittals.show', $transmittal));
        $this->assertNotSoftDeleted('transmittals', ['id' => $transmittal->id]);

        $this->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Final disposal of duplicate draft.'])
            ->assertRedirect(route('transmittals.index'));
        $this->actingAs(User::factory()->create(['transmittal_role' => 'staff']))
            ->delete(route('transmittals.force-delete', $transmittal->id), [
                'reason' => 'Unauthorized permanent deletion.',
                'confirm_tracking_number' => $transmittal->tracking_number,
            ])
            ->assertForbidden();
        $this->assertSoftDeleted('transmittals', ['id' => $transmittal->id]);
        $this->actingAs($administrator);
        $this->delete(route('transmittals.force-delete', $transmittal->id), [
            'reason' => 'Approved removal of a duplicate draft.',
            'confirm_tracking_number' => 'incorrect',
        ])->assertUnprocessable();

        $this->delete(route('transmittals.force-delete', $transmittal->id), [
            'reason' => 'Approved removal of a duplicate draft.',
            'confirm_tracking_number' => $transmittal->tracking_number,
        ])->assertRedirect(route('transmittals.index'));

        $this->assertDatabaseMissing('transmittals', ['id' => $transmittal->id]);
        $this->assertDatabaseHas('transmittal_lifecycle_archives', [
            'tracking_number' => $transmittal->tracking_number,
            'action' => 'permanently_deleted',
        ]);
        $archive = json_decode(
            DB::table('transmittal_lifecycle_archives')
                ->where('tracking_number', $transmittal->tracking_number)
                ->value('archived_record'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame('soft_deleted', collect($archive['history'])->last()['action']);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_transmittal_with_proof_evidence_cannot_be_permanently_deleted(): void
    {
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $transmittal = $this->pendingTransmittal('ORDO-2026-LIFECYCLE03');
        $recipient = $transmittal->recipients()->firstOrFail();
        $transmittal->proofs()->create([
            'recipient_id' => $recipient->id,
            'channel' => 'courier',
            'status' => 'uploaded',
            'transaction_reference' => 'TXN-PROTECTED',
        ]);
        $this->actingAs($administrator)
            ->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Archive completed record.'])
            ->assertRedirect();

        $this->delete(route('transmittals.force-delete', $transmittal->id), [
            'reason' => 'Must not remove proof history.',
            'confirm_tracking_number' => $transmittal->tracking_number,
        ])->assertStatus(409);

        $this->assertSoftDeleted('transmittals', ['id' => $transmittal->id]);
        $this->assertDatabaseHas('proof_of_transmissions', [
            'transmittal_id' => $transmittal->id,
            'transaction_reference' => 'TXN-PROTECTED',
        ]);
        $this->assertDatabaseMissing('transmittal_lifecycle_archives', [
            'tracking_number' => $transmittal->tracking_number,
        ]);
    }

    public function test_permanent_delete_surfaces_private_file_cleanup_failures_and_retains_the_archive(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $transmittal = $this->pendingTransmittal('ORDO-2026-LIFECYCLE04');
        $path = "transmittals/{$transmittal->id}/cleanup-failure.pdf";
        $localDisk = Storage::disk('local');
        $localDisk->put($path, 'private content');
        $transmittal->documents()->create([
            'original_name' => 'cleanup-failure.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('private content'),
            'sha256' => hash('sha256', 'private content'),
        ]);
        $this->actingAs($administrator)
            ->delete(route('transmittals.destroy', $transmittal), ['reason' => 'Duplicate draft.'])
            ->assertRedirect();

        $filesystem = \Mockery::mock();
        $filesystem->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $filesystem->shouldReceive('delete')->once()->with($path)->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($filesystem);
        Log::shouldReceive('error')
            ->once()
            ->with('Permanent transmittal deletion left private files behind.', \Mockery::on(
                fn (array $context): bool => $context['tracking_number'] === $transmittal->tracking_number
                    && $context['failed_paths'] === [$path]
            ));

        $this->delete(route('transmittals.force-delete', $transmittal->id), [
            'reason' => 'Approved duplicate cleanup.',
            'confirm_tracking_number' => $transmittal->tracking_number,
        ])->assertStatus(500);

        $this->assertDatabaseMissing('transmittals', ['id' => $transmittal->id]);
        $this->assertDatabaseHas('transmittal_lifecycle_archives', [
            'tracking_number' => $transmittal->tracking_number,
            'action' => 'permanently_deleted',
        ]);
        $localDisk->assertExists($path);
    }

    public function test_only_an_administrator_can_approve_a_public_submission(): void
    {
        $transmittal = $this->pendingTransmittal('ORDO-2026-APPROVE1');
        $transmittal->forceFill([
            'direction' => 'receive',
            'transmittal_date' => '2026-10-14',
            'origin_office' => 'Client Office',
            'recipient_address' => '10 Sample Road',
            'related_company' => 'Sample Client',
            'delivery_method' => 'electronic',
            'delivery_type' => 'physical',
            'delivery_actions' => ['delivery', 'pick_up'],
            'prepared_by_name' => 'Office Preparer',
            'operations_manager' => 'Operations Lead',
            'delivered_by' => 'Office Courier',
            'received_by' => 'Office Clerk',
            'processing_company' => 'Sample Client',
        ])->save();
        $transmittal->items()->create([
            'position' => 0,
            'particular' => 'Signed plans',
            'unique_id' => 'PLAN-20',
            'quantity' => '2',
            'description' => 'Stamped plan sheets',
            'remarks' => 'Return one copy',
        ]);
        $regularUser = User::factory()->create(['transmittal_role' => 'preparer']);

        $this->post(route('transmittals.approve', $transmittal))
            ->assertRedirect(route('login'));
        $this->actingAs($regularUser)
            ->post(route('transmittals.approve', $transmittal))
            ->assertForbidden();

        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->post(route('transmittals.approve', $transmittal))
            ->assertRedirect(route('transmittals.show', $transmittal));

        $transmittal->refresh();
        $authority = ApprovalAuthorityCode::query()->where('transmittal_id', $transmittal->id)->firstOrFail();
        $this->assertSame('approved', $transmittal->status);
        $this->assertSame($authority->code, $transmittal->approval_authority_code);
        $this->assertSame('Public submitter', $authority->frozen_snapshot['prepared_by']['name']);
        $this->assertSame('receive', $authority->frozen_snapshot['direction']);
        $this->assertSame('2026-10-14', $authority->frozen_snapshot['transmittal_date']);
        $this->assertSame('Client Office', $authority->frozen_snapshot['origin_office']);
        $this->assertSame('10 Sample Road', $authority->frozen_snapshot['recipient_address']);
        $this->assertSame('Sample Client', $authority->frozen_snapshot['related_company']);
        $this->assertSame('physical', $authority->frozen_snapshot['delivery_type']);
        $this->assertSame(['delivery', 'pick_up'], $authority->frozen_snapshot['delivery_actions']);
        $this->assertSame('Office Preparer', $authority->frozen_snapshot['prepared_by_name']);
        $this->assertSame($administrator->name, $authority->frozen_snapshot['approved_by_name']);
        $this->assertSame('PLAN-20', $authority->frozen_snapshot['items'][0]['unique_id']);
        $this->assertSame($transmittal->approved_snapshot, $authority->frozen_snapshot);
        $this->assertSame(
            hash('sha256', json_encode($authority->frozen_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            $authority->snapshot_hash
        );

        try {
            $transmittal->items()->firstOrFail()->update(['particular' => 'Changed after approval']);
            $this->fail('Approved transmittal items must remain immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Approved transmittal items are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $transmittal->forceFill(['subject' => 'Changed after approval'])->save();
    }

    public function test_administrator_can_reject_with_a_persisted_reason_and_audit_event(): void
    {
        $transmittal = $this->pendingTransmittal('ORDO-2026-REJECT01');
        $regularUser = User::factory()->create(['transmittal_role' => 'preparer']);

        $this->actingAs($regularUser)
            ->post(route('transmittals.reject', $transmittal), ['rejection_reason' => 'Missing details'])
            ->assertForbidden();
        $this->assertSame('pending_approval', $transmittal->fresh()->status);

        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->post(route('transmittals.reject', $transmittal), ['rejection_reason' => 'Missing recipient details.'])
            ->assertRedirect(route('transmittals.show', $transmittal));

        $this->assertDatabaseHas('transmittals', [
            'id' => $transmittal->id,
            'status' => 'rejected',
            'rejected_by' => $administrator->id,
            'rejection_reason' => 'Missing recipient details.',
        ]);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'actor_id' => $administrator->id,
            'action' => 'rejected',
        ]);
        $this->get(route('transmittals.show', $transmittal))
            ->assertRedirect(route('transmittals.workspace', $transmittal));
        $this->get(route('transmittals.workspace', $transmittal))
            ->assertOk()
            ->assertSee('Missing recipient details.');
    }

    public function test_administrator_login_does_not_allow_regular_users_and_has_no_signup_flow(): void
    {
        $regularUser = User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'correct-horse-battery',
            'transmittal_role' => 'preparer',
        ]);

        $this->post(route('login.store'), [
            'email' => $regularUser->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $administrator = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
            'transmittal_role' => 'administrator',
        ]);

        $this->post(route('login.store'), [
            'email' => $administrator->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('transmittals.index'));

        $this->assertAuthenticatedAs($administrator);
    }

    public function test_brevo_notice_and_recipient_acknowledgment_are_auditable(): void
    {
        config([
            'ordo.brevo.api_key' => 'test-api-key',
            'ordo.brevo.sender_email' => 'transmittals@example.test',
            'app.url' => 'http://localhost',
        ]);
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<brevo-message-1>'], 201)]);

        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $transmittal = $this->pendingTransmittal('ORDO-2026-EMAIL001');
        $recipient = $transmittal->recipients()->firstOrFail();
        $recipient->update([
            'name' => 'External Contact',
            'email' => 'external@example.test',
        ]);

        $this->actingAs($administrator)
            ->post(route('transmittals.approve', $transmittal))
            ->assertRedirect();
        $this->post(route('transmittals.transmit', $transmittal))->assertRedirect();

        $recipient->refresh();
        $this->assertNotNull($recipient->acknowledgment_token_hash);
        $this->assertDatabaseHas('proof_of_transmissions', [
            'transmittal_id' => $transmittal->id,
            'recipient_id' => $recipient->id,
            'provider_message_id' => '<brevo-message-1>',
            'status' => 'sent',
        ]);
        $this->post(route('transmittals.transmit', $transmittal))
            ->assertRedirect()
            ->assertSessionHas('status', 'All recipients have already been notified.');
        Http::assertSentCount(1);

        $requestBody = Http::recorded()->first()[0]->data();
        preg_match('~/acknowledgments/([A-Za-z0-9]+)~', $requestBody['htmlContent'], $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $token = $matches[1];
        $this->assertSame(hash('sha256', $token), $recipient->acknowledgment_token_hash);

        $this->get(route('acknowledgments.show', $token))->assertOk()->assertSee('Transmittal subject');
        $this->get(route('acknowledgments.show', $token))->assertOk();
        $this->assertDatabaseHas('proof_of_transmissions', [
            'transmittal_id' => $transmittal->id,
            'recipient_id' => $recipient->id,
            'transaction_reference' => $transmittal->proofs()->where('status', 'sent')->value('transaction_reference'),
            'status' => 'viewed',
        ]);
        $this->assertDatabaseHas('external_acknowledgments', [
            'transmittal_id' => $transmittal->id,
            'event' => 'viewed',
        ]);
        $this->post(route('acknowledgments.store', $token), [
            'signed_name' => 'External Contact',
            'comments' => 'Received all files.',
        ])->assertRedirect(route('acknowledgments.show', $token));

        $this->assertDatabaseHas('transmittal_receipts', [
            'transmittal_id' => $transmittal->id,
            'recipient_id' => $recipient->id,
            'signed_name' => 'External Contact',
        ]);
        $this->assertDatabaseHas('external_acknowledgments', [
            'transmittal_id' => $transmittal->id,
            'event' => 'acknowledged',
        ]);
        $receipt = $recipient->fresh()->receipt;
        $this->assertStringStartsWith('ORDO-TRR-', $receipt->receipt_code);
        $this->assertDatabaseHas('proof_of_transmissions', [
            'transmittal_id' => $transmittal->id,
            'recipient_id' => $recipient->id,
            'status' => 'acknowledged',
        ]);
        $acknowledgmentProof = $recipient->proofs()
            ->where('status', 'acknowledged')
            ->firstOrFail();
        $receiptHtml = view('transmittals.receipt', [
            'transmittal' => $transmittal,
            'receipt' => $receipt,
            'snapshot' => $receipt->snapshot,
            'acknowledgmentProof' => $acknowledgmentProof,
        ])->render();
        $this->assertStringContainsString('OFFICIAL ELECTRONIC RECEIPT', $receiptHtml);
        $this->assertStringContainsString('TRANSMITTAL REFERENCE (TRN)', $receiptHtml);
        $this->assertStringContainsString($receipt->receipt_code, $receiptHtml);
        $this->assertStringContainsString($acknowledgmentProof->transaction_reference, $receiptHtml);

        $this->get(route('transmittals.receipts.pdf', [$transmittal, $receipt]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF', false);
        $this->get(route('acknowledgments.receipts.pdf', [$token, $receipt]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertSee('%PDF', false);
        $this->assertSame('acknowledged', $transmittal->fresh()->status);
        $this->assertInstanceOf(TransmittalReceipt::class, $recipient->fresh()->receipt);
    }

    public function test_public_transmittal_record_exports_a_pdf_with_a_confidential_cover(): void
    {
        $transmittal = $this->pendingTransmittal('ORDO-2026-PDF00001');
        $transmittal->forceFill([
            'transmittal_date' => '2026-03-19',
            'origin_office' => 'ORDO Records Office',
            'recipient_address' => '10 Example Street',
            'related_company' => 'Example Company',
            'delivery_type' => 'electronic',
            'delivery_method' => 'electronic',
            'delivery_actions' => ['email', 'delivery'],
            'prepared_by_name' => 'Jordan Preparer',
            'prepared_at' => now(),
            'delivered_by' => 'Taylor Delivery',
            'received_by' => 'Alex Recipient',
            'operations_manager' => 'Morgan Operations',
            'processing_company' => 'Example Company',
        ])->save();
        $transmittal->items()->create([
            'position' => 1,
            'particular' => 'Signed plans',
            'unique_id' => 'PLAN-001',
            'quantity' => '2',
            'description' => 'Approved plan sheets',
            'remarks' => 'Handle confidentially',
        ]);

        $transmittal->load(['preparer', 'approver', 'recipients', 'items.document']);
        $html = view('transmittals.pdf', compact('transmittal'))->render();
        $coverPosition = strpos($html, 'Confidentiality Notice');
        $formPosition = strpos($html, 'OFFICIAL TRANSMITTAL FORM');
        $this->assertNotFalse($coverPosition);
        $this->assertNotFalse($formPosition);
        $this->assertLessThan($formPosition, $coverPosition);
        foreach ([
            'REF', 'Date', 'Mode', 'FROM / OFFICE', 'TO',
            'Delivery Type / Actions', 'Electronic Method / Recipient Email',
            'Particular', 'Unique ID', 'Qty', 'Description', 'Remarks', 'Attachment',
            'Signed plans', 'PLAN-001', 'PREPARED BY', 'DELIVERED BY', 'APPROVED BY',
            'RECEIVED BY', 'OPERATIONS MANAGER', 'PROCESSING FOR COMPANY / ORGANIZATION',
            '10 Example Street', 'Example Company',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $renderedPdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('transmittals.pdf', compact('transmittal'))
            ->setPaper('a4');
        $renderedPdf->render();
        $this->assertSame(2, $renderedPdf->getDomPDF()->getCanvas()->get_page_count());

        $this->get(route('transmittals.pdf', $transmittal))->assertNotFound();

        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->get(route('transmittals.pdf', $transmittal))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertSee('%PDF', false);
    }

    public function test_pdf_keeps_transmission_evidence_after_the_official_form_page(): void
    {
        $transmittal = $this->pendingTransmittal('ORDO-2026-PDF00002');
        $recipient = $transmittal->recipients()->firstOrFail();
        $transmittal->proofs()->create([
            'recipient_id' => $recipient->id,
            'channel' => 'email',
            'status' => 'sent',
            'transaction_reference' => 'TXN-PDF-EVIDENCE',
            'transmitted_at' => now(),
            'provider_message_id' => 'provider-pdf-evidence',
        ]);
        $transmittal->load(['preparer', 'approver', 'rejector', 'recipients', 'documents', 'items.document', 'proofs.recipient', 'receipts']);

        $html = view('transmittals.pdf', compact('transmittal'))->render();
        $formPosition = strpos($html, 'OFFICIAL TRANSMITTAL FORM');
        $evidencePosition = strpos($html, 'Delivery and approval evidence');

        $this->assertNotFalse($formPosition);
        $this->assertNotFalse($evidencePosition);
        $this->assertGreaterThan($formPosition, $evidencePosition);
        $this->assertStringContainsString('TXN-PDF-EVIDENCE', $html);
        $this->assertStringContainsString('provider-pdf-evidence', $html);
    }

    public function test_secure_recipient_link_can_only_download_attachments_for_its_transmittal(): void
    {
        Storage::fake('local');

        $transmittal = $this->pendingTransmittal('ORDO-2026-PRIVATE001');
        $otherTransmittal = $this->pendingTransmittal('ORDO-2026-PRIVATE002');
        $contents = 'confidential source document';
        $path = "transmittals/{$transmittal->id}/private-contract.pdf";
        Storage::disk('local')->put($path, $contents);
        $document = $transmittal->documents()->create([
            'category' => 'document',
            'original_name' => 'private-contract.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);
        $otherPath = "transmittals/{$otherTransmittal->id}/other.pdf";
        Storage::disk('local')->put($otherPath, $contents);
        $otherDocument = $otherTransmittal->documents()->create([
            'category' => 'document',
            'original_name' => 'other.pdf',
            'storage_path' => $otherPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);

        $token = str_repeat('a', 64);
        $recipient = $transmittal->recipients()->firstOrFail();
        $recipient->forceFill([
            'acknowledgment_token_hash' => hash('sha256', $token),
            'acknowledgment_expires_at' => now()->addDay(),
            'sent_at' => now(),
        ])->save();

        $this->get(route('transmittals.documents.download', [$transmittal, $document]))
            ->assertNotFound();
        $this->get(route('acknowledgments.show', $token))
            ->assertOk()
            ->assertSee('private-contract.pdf');
        $this->get(route('acknowledgments.documents.download', [$token, $document]))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->get(route('acknowledgments.documents.download', [$token, $otherDocument]))
            ->assertNotFound();
        $this->get(route('acknowledgments.documents.download', [str_repeat('b', 64), $document]))
            ->assertNotFound();
        $recipient->forceFill(['acknowledgment_expires_at' => now()->subMinutes(1)])->save();
        $this->get(route('acknowledgments.documents.download', [$token, $document]))
            ->assertNotFound();
        $recipient->forceFill(['acknowledgment_expires_at' => now()->addDay()])->save();
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)
            ->post(route('transmittals.recipients.revoke-link', [$transmittal, $recipient]), [
                'reason' => 'Recipient address was entered incorrectly.',
            ])
            ->assertRedirect(route('transmittals.show', $transmittal));
        $this->get(route('acknowledgments.show', $token))->assertNotFound();
        $this->get(route('acknowledgments.documents.download', [$token, $document]))->assertNotFound();
        $this->post(route('acknowledgments.store', $token), ['signed_name' => 'Recipient'])->assertNotFound();
        $this->assertNotNull($recipient->fresh()->acknowledgment_revoked_at);
        $this->assertDatabaseHas('external_acknowledgments', [
            'recipient_id' => $recipient->id,
            'event' => 'link_revoked',
        ]);
    }

    public function test_administrator_can_upload_and_edit_manual_video_proof_with_audited_sender_details(): void
    {
        Storage::fake('local');

        $transmittal = $this->pendingTransmittal('ORDO-2026-MANUAL001');
        $recipient = $transmittal->recipients()->firstOrFail();
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $regularUser = User::factory()->create(['transmittal_role' => 'preparer']);

        $this->actingAs($administrator)
            ->post(route('transmittals.approve', $transmittal))
            ->assertRedirect();

        $payload = [
            'recipient_id' => $recipient->id,
            'proof_type' => 'delivery_receipt',
            'receiver_name' => 'Jordan Receiver',
            'sender_name' => $administrator->name,
            'sender_position' => 'Records Officer',
            'location' => 'Cebu City Office',
            'device_category' => 'laptop',
            'device' => 'Office laptop',
            'channel' => 'courier',
            'reference_number' => 'POD-7781',
            'transmission_at' => '2026-10-14T09:30',
            'notes' => 'Package delivered to reception.',
            'proof' => UploadedFile::fake()->create('delivery-proof.mp4', 20, 'video/mp4'),
        ];

        $this->actingAs($administrator)
            ->post(route('transmittals.proofs.store', $transmittal), $payload)
            ->assertRedirect(route('transmittals.show', $transmittal))
            ->assertSessionHas('status', 'Proof of Transmission uploaded and recorded.');
        $this->assertDatabaseHas('proof_of_transmissions', [
            'transmittal_id' => $transmittal->id,
            'status' => 'uploaded',
        ]);

        $this->actingAs($regularUser)
            ->post(route('transmittals.proofs.store', $transmittal), $payload)
            ->assertForbidden();

        $proof = $transmittal->proofs()->where('status', 'uploaded')->firstOrFail();
        $proof->load('document');

        $this->assertSame('courier', $proof->channel);
        $this->assertSame('Jordan Receiver', $proof->provider_metadata['receiver_name']);
        $this->assertSame('POD-7781', $proof->provider_metadata['reference_number']);
        $this->assertSame('2026-10-14T09:30', $proof->transmitted_at->format('Y-m-d\\TH:i'));
        $this->assertSame($administrator->name, $proof->provider_metadata['sender_name']);
        $this->assertSame('Records Officer', $proof->provider_metadata['sender_position']);
        $this->assertSame('Cebu City Office', $proof->provider_metadata['location']);
        $this->assertSame('user_entered', $proof->provider_metadata['location_source']);
        $this->assertSame('laptop', $proof->provider_metadata['device_category']);
        $this->assertSame('Office laptop', $proof->provider_metadata['device']);
        $this->assertSame('user_entered', $proof->provider_metadata['device_source']);
        $this->assertSame('manual_upload', $proof->provider_metadata['source']);
        $this->assertSame('manual', $proof->provider_metadata['entry_mode']);
        $this->assertSame('Jordan Receiver', $proof->provider_metadata['reviewed_values']['receiver_name']);
        $this->assertSame($administrator->id, $proof->provider_metadata['reviewed_by']);
        $this->assertSame($administrator->id, $proof->provider_metadata['initial_confirmation']['user_id']);
        $this->assertNotEmpty($proof->provider_metadata['initial_confirmation']['at']);
        $this->assertSame('delivery-proof.mp4', $proof->document->original_name);
        $this->assertStringStartsWith('TXN-', $proof->transaction_reference);
        Storage::disk('local')->assertExists($proof->document->storage_path);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'proof_uploaded',
        ]);

        $this->actingAs($administrator)
            ->post(route('transmittals.proofs.store', $transmittal), array_merge($payload, [
                'receiver_name' => 'Second Receiver',
                'sender_name' => 'Second Sender',
                'reference_number' => 'POD-SECOND',
                'proof' => UploadedFile::fake()->create('second-delivery-proof.mp4', 20, 'video/mp4'),
            ]))
            ->assertRedirect(route('transmittals.show', $transmittal));
        $this->assertSame(2, $transmittal->proofs()->count());
        $editableProof = $transmittal->proofs()->latest('id')->firstOrFail();
        $this->actingAs($regularUser)
            ->put(route('transmittals.proofs.update', [$transmittal, $editableProof]), [
                'recipient_id' => $recipient->id,
                'proof_type' => 'delivery_receipt',
                'receiver_name' => 'Jordan Receiver',
                'sender_name' => 'Corrected Sender',
                'sender_position' => 'Office Manager',
                'location' => 'Mandaue Office',
                'device_category' => 'mobile',
                'device' => 'Work phone',
                'channel' => 'registered_mail',
                'reference_number' => 'POD-7781-CORRECTED',
                'transmission_at' => '2026-10-14T10:45',
                'edit_reason' => 'Corrected sender and transmission details.',
            ])
            ->assertForbidden();
        $this->actingAs($administrator)
            ->put(route('transmittals.proofs.update', [$transmittal, $editableProof]), [
                'recipient_id' => $recipient->id,
                'proof_type' => 'delivery_receipt',
                'receiver_name' => 'Jordan Receiver',
                'sender_name' => 'Corrected Sender',
                'sender_position' => 'Office Manager',
                'location' => 'Mandaue Office',
                'device_category' => 'mobile',
                'device' => 'Work phone',
                'channel' => 'registered_mail',
                'reference_number' => 'POD-7781-CORRECTED',
                'transmission_at' => '2026-10-14T10:45',
                'edit_reason' => 'Corrected sender and transmission details.',
            ])
            ->assertRedirect(route('transmittals.show', $transmittal));

        $editableProof->refresh();
        $this->assertSame($administrator->id, $editableProof->sent_by, 'The authenticated audit actor must remain unchanged.');
        $this->assertSame('Corrected Sender', $editableProof->provider_metadata['sender_name']);
        $this->assertSame('Mandaue Office', $editableProof->provider_metadata['sender_location']);
        $this->assertSame('mobile', $editableProof->provider_metadata['device_category']);
        $this->assertSame('registered_mail', $editableProof->channel);
        $this->assertSame('laptop', $editableProof->provider_metadata['edit_history'][0]['previous']['device_category']);
        $this->assertSame($administrator->id, $editableProof->provider_metadata['edit_history'][0]['edited_by']);
        $showResponse = $this->get(route('transmittals.show', $transmittal))->assertOk();
        $this->assertGreaterThan(0, preg_match_all('/<td class="proof-method-cell">(.*?)<\/td>/s', $showResponse->getContent(), $methodCells));
        $renderedMethods = array_map(fn (string $cell) => html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5), $methodCells[1]);
        $renderedMethod = collect($renderedMethods)->first(fn (string $cell) => str_contains($cell, 'Sender: Corrected Sender'));
        $this->assertNotNull($renderedMethod, 'The edited proof should render its details in the Transmission Method column.');
        $this->assertIsString($renderedMethod);
        $this->assertStringContainsString('Registered Mail', $renderedMethod);
        $this->assertStringContainsString('Sender: Corrected Sender', $renderedMethod);
        $this->assertStringContainsString('Location: Mandaue Office', $renderedMethod);
        $this->assertStringContainsString('Device: Mobile · Work phone', $renderedMethod);
        $this->assertDatabaseHas('transmittal_histories', [
            'transmittal_id' => $transmittal->id,
            'action' => 'proof_updated',
        ]);
        $this->expectException(LogicException::class);
        $editableProof->update(['sent_by' => $regularUser->id]);
    }

    public function test_ocr_unavailability_is_reported_without_claiming_success(): void
    {
        config([
            'ordo.ocr.binary' => 'ordo-tesseract-that-does-not-exist',
            'ordo.ocr.pdf_binary' => 'ordo-pdftoppm-that-does-not-exist',
        ]);

        $this->postJson(route('transmittals.analyze'), [
            'documents' => [UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf')],
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'PDF OCR could not rasterize this document. Verify the PDF and Poppler installation.');
    }

    public function test_proof_ocr_corrections_are_confirmed_and_preserve_original_suggestions(): void
    {
        Storage::fake('local');
        Process::fake([
            '*' => Process::result(output: implode("\n", [
                'Reference: OCR-REF-41',
                'To: OCR Receiver',
                'Date: 2026-10-13',
                'Location: OCR document location',
                'Device used: OCR scanning terminal',
            ])),
        ]);
        $imageContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/NioAAAAASUVORK5CYII=', true);
        $transmittal = $this->pendingTransmittal('ORDO-2026-PROOFOCR01');
        $recipient = $transmittal->recipients()->firstOrFail();
        $administrator = User::factory()->create(['transmittal_role' => 'administrator']);
        $this->actingAs($administrator)->post(route('transmittals.approve', $transmittal))->assertRedirect();

        $analysis = $this->postJson(route('transmittals.analyze'), [
            'documents' => [UploadedFile::fake()->createWithContent('proof.png', $imageContents)],
        ])->assertOk();
        $ocrToken = $analysis->json('documents.0.analysis_token');

        $this->actingAs($administrator)
            ->post(route('transmittals.proofs.store', $transmittal), [
                'recipient_id' => $recipient->id,
                'proof_type' => 'delivery_receipt',
                'receiver_name' => 'Corrected Receiver',
                'sender_name' => $administrator->name,
                'sender_position' => 'Records Officer',
                'location' => 'Cebu City Office',
                'device_category' => 'desktop',
                'device' => 'Records desk computer',
                'channel' => 'courier',
                'reference_number' => 'MANUAL-REF-42',
                'transmission_at' => '2026-10-14T11:15',
                'notes' => 'Corrected after comparing with the signed copy.',
                'ocr_token' => $ocrToken,
                'ocr_reviewed' => '1',
                'proof' => UploadedFile::fake()->createWithContent('proof.png', $imageContents),
            ])
            ->assertRedirect(route('transmittals.show', $transmittal));

        $proof = $transmittal->proofs()->where('status', 'uploaded')->firstOrFail();
        $this->assertSame('OCR-REF-41', $proof->provider_metadata['ocr_suggestions']['reference']);
        $this->assertSame('OCR Receiver', $proof->provider_metadata['ocr_suggestions']['recipient_name']);
        $this->assertSame('Corrected Receiver', $proof->provider_metadata['reviewed_values']['receiver_name']);
        $this->assertSame('MANUAL-REF-42', $proof->provider_metadata['reviewed_values']['reference_number']);
        $this->assertSame('tesseract_ocr', $proof->provider_metadata['ocr_field_metadata']['reference']['source']);
        $this->assertTrue($proof->provider_metadata['ocr_confirmed']);
        $this->assertSame('ocr_assisted_upload', $proof->provider_metadata['source']);
        $this->assertSame($administrator->id, $proof->provider_metadata['reviewed_by']);
        $this->assertSame('Cebu City Office', $proof->provider_metadata['location'], 'OCR must not replace the sender-entered current location.');
        $this->assertSame('Records desk computer', $proof->provider_metadata['device'], 'OCR must not replace the sender-entered device.');
        $this->assertSame('OCR document location', $proof->provider_metadata['ocr_suggestions']['location']);
        $this->assertSame('OCR scanning terminal', $proof->provider_metadata['ocr_suggestions']['device']);
        $showResponse = $this->actingAs($administrator)->get(route('transmittals.show', $transmittal))->assertOk();
        $showResponse->assertSee('Records Officer')->assertSee('Cebu City Office')->assertSee('Records desk computer');
        $this->assertGreaterThan(0, preg_match_all('/<td class="proof-method-cell">(.*?)<\/td>/s', $showResponse->getContent(), $methodCells));
        $renderedMethods = array_map(fn (string $cell) => html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5), $methodCells[1]);
        $renderedMethod = collect($renderedMethods)->first(fn (string $cell) => str_contains($cell, 'Sender: '.$administrator->name));
        $this->assertNotNull($renderedMethod, 'The OCR-assisted proof should render its confirmed sender details in the Transmission Method column.');
        $this->assertIsString($renderedMethod);
        $this->assertStringContainsString('Courier', $renderedMethod);
        $this->assertStringContainsString('Sender: '.$administrator->name, $renderedMethod);
        $this->assertStringContainsString('Location: Cebu City Office', $renderedMethod);
        $this->assertStringContainsString('Device: Desktop · Records desk computer', $renderedMethod);
    }

    public function test_ocr_returns_expanded_fields_with_source_and_confidence_metadata(): void
    {
        Process::fake([
            '*' => Process::result(output: implode("\n", [
                'Reference: REF-991',
                'Unique ID: DOC-17',
                'Subject: Revised site drawing',
                'Description: Updated site plan for review',
                'Company: Example Corporation',
                'To: Jordan Recipient',
                'jordan@example.test',
                'Address: 44 Oak Road',
                'Date: 2026-10-14',
            ])),
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/NioAAAAASUVORK5CYII=', true);

        $this->postJson(route('transmittals.analyze'), [
            'documents' => [UploadedFile::fake()->createWithContent('transmittal.png', $png)],
        ])
            ->assertOk()
            ->assertJsonPath('documents.0.fields.reference', 'REF-991')
            ->assertJsonPath('documents.0.fields.unique_id', 'DOC-17')
            ->assertJsonPath('documents.0.fields.subject', 'Revised site drawing')
            ->assertJsonPath('documents.0.fields.description', 'Updated site plan for review')
            ->assertJsonPath('documents.0.fields.company', 'Example Corporation')
            ->assertJsonPath('documents.0.fields.recipient_name', 'Jordan Recipient')
            ->assertJsonPath('documents.0.fields.recipient_email', 'jordan@example.test')
            ->assertJsonPath('documents.0.fields.recipient_address', '44 Oak Road')
            ->assertJsonPath('documents.0.field_metadata.company.source', 'tesseract_ocr')
            ->assertJsonPath('documents.0.field_metadata.company.confidence', 'medium')
            ->assertJsonPath('documents.0.field_metadata.recipient_email.confidence', 'high');
    }

    public function test_resumable_uploads_verify_order_retry_identically_and_reject_conflicting_chunks(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 4]);

        $startResponse = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'package.pdf',
            'size' => 8,
            'type' => 'application/pdf',
        ])->assertCreated();
        $sessionId = $this->app['session']->driver()->getId();
        $this->withCookie(config('session.cookie'), $sessionId);
        $uploadId = $startResponse
            ->assertJsonPath('chunk_count', 2)
            ->assertJsonMissingPath('upload_token')
            ->json('upload_id');
        $startResponse->assertSessionHas("transmittal_upload_tokens.{$uploadId}");

        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'ABCD'),
        ])->assertOk()->assertJsonPath('retry', false);
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'ABCD'),
        ])->assertOk()->assertJsonPath('retry', true);
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'WXYZ'),
        ])->assertStatus(409);
        $this->post(route('transmittals.uploads.complete', $uploadId))
            ->assertStatus(409);

        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 1]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'EFGH'),
        ])->assertOk();
        $completed = $this->post(route('transmittals.uploads.complete', $uploadId));
        $this->assertSame(200, $completed->status(), $completed->getContent());
        $completed->assertJsonPath('status', 'completed')
            ->assertJsonPath('sha256', hash('sha256', 'ABCDEFGH'));
        $this->post(route('transmittals.uploads.complete', $uploadId))
            ->assertOk()
            ->assertJsonPath('sha256', hash('sha256', 'ABCDEFGH'));
    }

    public function test_resumable_uploads_enforce_chunk_count_type_and_exact_chunk_size(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 1, 'ordo.upload.max_chunks' => 2]);

        $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'too-many-chunks.pdf',
            'size' => 3,
            'type' => 'application/pdf',
        ])->assertUnprocessable();

        config(['ordo.upload.chunk_bytes' => 4, 'ordo.upload.max_chunks' => 4096]);
        $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'unsupported.exe',
            'size' => 4,
            'type' => 'application/octet-stream',
        ])->assertUnprocessable();

        config(['ordo.upload.expiry_hours' => 0]);
        $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'invalid-expiry.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertServerError();

        config(['ordo.upload.expiry_hours' => 24, 'ordo.upload.completion_lock_seconds' => 299]);
        $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'invalid-lock.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertServerError();

        config(['ordo.upload.completion_lock_seconds' => 3600]);
        $start = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'valid.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertCreated();
        $uploadId = $start->json('upload_id');
        $this->withCookie(config('session.cookie'), $this->app['session']->driver()->getId());

        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'BAD'),
        ])->assertUnprocessable();
    }

    public function test_resumable_upload_chunks_are_bound_to_the_creating_browser_session(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 4]);
        $uploadId = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'package.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertCreated()->json('upload_id');

        $validToken = $this->app['session']->driver()->get("transmittal_upload_tokens.{$uploadId}");
        $this->assertIsString($validToken);
        $this->withCookie(config('session.cookie'), str_repeat('a', 40))
            ->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
                'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'DATA'),
            ])
            ->assertNotFound();

        $this->expectException(NotFoundHttpException::class);
        app(ResumableTransmittalUploadService::class)
            ->ownedUpload(hash('sha256', 'another-session'), $uploadId, $validToken);
    }

    public function test_expired_resumable_uploads_are_removed_from_private_storage_by_cleanup(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 4, 'ordo.upload.expiry_hours' => 1]);
        $start = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'package.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertCreated();
        $uploadId = $start->json('upload_id');
        $sessionId = $this->app['session']->driver()->getId();
        $this->withCookie(config('session.cookie'), $sessionId);
        $this->assertNull($start->json('upload_token'));
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'DATA'),
        ])->assertOk();
        Storage::disk('local')->assertExists("transmittal-upload-chunks/{$uploadId}/0.part");

        $this->travel(2)->hours();
        $this->artisan('ordo:uploads:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('transmittal_upload_sessions', ['upload_id' => $uploadId]);
        $this->assertFalse(Storage::disk('local')->directoryExists("transmittal-upload-chunks/{$uploadId}"));
    }

    public function test_resumable_upload_activity_renews_expiry_before_cleanup(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 4, 'ordo.upload.expiry_hours' => 1]);
        $start = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'active.pdf',
            'size' => 4,
            'type' => 'application/pdf',
        ])->assertCreated();
        $uploadId = $start->json('upload_id');
        $sessionId = $this->app['session']->driver()->getId();
        $this->withCookie(config('session.cookie'), $sessionId);

        $this->travel(50)->minutes();
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', 'DATA'),
        ])->assertOk();

        $this->travel(20)->minutes();
        $this->artisan('ordo:uploads:cleanup')->assertExitCode(0);
        $this->assertDatabaseHas('transmittal_upload_sessions', [
            'upload_id' => $uploadId,
            'status' => 'uploading',
        ]);
        Storage::disk('local')->assertExists("transmittal-upload-chunks/{$uploadId}/0.part");
    }

    public function test_smart_upload_can_create_a_draft_from_a_verified_resumable_file(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 8]);
        $contents = "%PDF-1.4\n";
        $startResponse = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'resumable.pdf',
            'size' => strlen($contents),
            'type' => 'application/pdf',
        ])->assertCreated();
        $startResponse->assertJsonMissingPath('upload_token');
        $sessionId = $this->app['session']->driver()->getId();
        $this->withCookie(config('session.cookie'), $sessionId);
        $uploadId = $startResponse->json('upload_id');
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', substr($contents, 0, 8)),
        ])->assertOk();
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 1]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', substr($contents, 8)),
        ])->assertOk();
        $this->post(route('transmittals.uploads.complete', $uploadId))->assertOk();

        $payload = [
            'subject' => 'Resumable package',
            'upload_ids' => [$uploadId],
            'upload_categories' => ['document'],
            'upload_ocr_tokens' => [''],
        ];
        $this->post(route('transmittals.smart-upload.store'), $payload)->assertRedirect();

        $transmittal = Transmittal::query()->firstOrFail();
        $this->assertDatabaseHas('transmittal_upload_sessions', [
            'upload_id' => $uploadId,
            'status' => 'consumed',
        ]);
        $this->assertDatabaseHas('transmittal_documents', [
            'transmittal_id' => $transmittal->id,
            'original_name' => 'resumable.pdf',
        ]);
        Storage::disk('local')->assertExists($transmittal->documents()->firstOrFail()->storage_path);
        $this->post(route('transmittals.smart-upload.store'), $payload)->assertNotFound();
        $this->assertDatabaseCount('transmittals', 1);
    }

    public function test_smart_upload_rejects_a_completed_file_after_integrity_changes(): void
    {
        Storage::fake('local');
        config(['ordo.upload.chunk_bytes' => 8]);
        $contents = "%PDF-1.4\n";
        $start = $this->postJson(route('transmittals.uploads.start'), [
            'name' => 'tampered.pdf',
            'size' => strlen($contents),
            'type' => 'application/pdf',
        ])->assertCreated();
        $sessionId = $this->app['session']->driver()->getId();
        $this->withCookie(config('session.cookie'), $sessionId);
        $uploadId = $start->json('upload_id');
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 0]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', substr($contents, 0, 8)),
        ])->assertOk();
        $this->post(route('transmittals.uploads.chunks.store', [$uploadId, 1]), [
            'chunk' => UploadedFile::fake()->createWithContent('part.bin', substr($contents, 8)),
        ])->assertOk();
        $this->post(route('transmittals.uploads.complete', $uploadId))->assertOk();

        $upload = TransmittalUpload::query()->where('upload_id', $uploadId)->firstOrFail();
        Storage::disk('local')->put($upload->storage_path, 'tampered');
        $this->post(route('transmittals.smart-upload.store'), [
            'subject' => 'Tampered package',
            'upload_ids' => [$uploadId],
            'upload_categories' => ['document'],
            'upload_ocr_tokens' => [''],
        ])->assertStatus(409);

        $this->assertDatabaseCount('transmittals', 0);
        $this->assertDatabaseHas('transmittal_upload_sessions', [
            'upload_id' => $uploadId,
            'status' => 'completed',
        ]);
    }

    private function pendingTransmittal(string $trackingNumber): Transmittal
    {
        $transmittal = Transmittal::query()->create([
            'tracking_number' => $trackingNumber,
            'subject' => 'Transmittal subject',
            'purpose' => 'For administrator review.',
            'confidentiality' => 'standard',
            'status' => 'pending_approval',
        ]);
        $transmittal->recipients()->create([
            'name' => 'Recipient',
            'email' => 'recipient@example.test',
        ]);

        return $transmittal;
    }
}
