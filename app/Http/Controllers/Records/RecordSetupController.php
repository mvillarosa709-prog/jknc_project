<?php

namespace App\Http\Controllers\Records;

use App\Enums\IntakeStatus;
use App\Enums\LinkRelationship;
use App\Enums\RecordFieldType;
use App\Enums\SourceType;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordClassification;
use App\Models\RecordField;
use App\Models\RecordIndex;
use App\Models\RecordIntakeItem;
use App\Models\RecordLink;
use App\Models\RecordSource;
use App\Services\CompanyContext;
use App\Services\Records\RecordSearchService;
use App\Support\GeneratedDocumentMappings;
use App\Support\RecordNumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The Records Management Setup modal.
 *
 * One controller serves the whole modal. Each tab is a fragment fetched when the
 * tab is opened, so the six tabs cost nothing on an ordinary page load — only
 * the tab the user actually opens is queried, and only that tab reaches the DOM.
 *
 * Everything is scoped to the active company through the context. The tab is
 * taken from the route, not from a payload, and an unknown tab is a 404 rather
 * than a blank pane, so a typo shows as a missing screen instead of an empty
 * modal that looks like a fault in the data.
 */
class RecordSetupController extends Controller
{
    /**
     * The tabs the modal offers, in the order the mock presents them.
     *
     * @var list<string>
     */
    public const TABS = [
        'classifications',
        'sources',
        'fields',
        'numbering',
        'usage',
        'integration',
    ];

    /**
     * The services the Core Pattern card names, and whether each one exists.
     *
     * Recordable and Brevo do not. They are listed because the pattern is the
     * contract other modules will build against, but a table that presented them
     * as wired would be describing something this codebase has not got.
     *
     * @var list<array{0: string, 1: string, 2: bool}>
     */
    private const WIRING = [
        ['Record Service', 'App\Services\Records\RecordService', true],
        ['Recordable', 'App\Models\Concerns\Recordable', false],
        ['Links', 'record_links · polymorphic', true],
        ['Storage', 'Laravel Storage disk: records', true],
        ['OCR / metadata jobs', 'Laravel Queue / Job', true],
        ['Email', 'Laravel Notifications → Brevo', false],
    ];

    /**
     * The events the engine raises, as this codebase actually names them.
     *
     * The mock recommends RecordCreated / RecordClassified / FileAdded and so
     * on. Most of those exist here under different names — there is no
     * RecordCreated, because creating a record and filing it are the same act —
     * so this lists the real set, which is what a module would subscribe to.
     *
     * @var list<string>
     */
    private const INTEGRATION_ACTIONS = [
        'record.filed',
        'record.linked',
        'record.unlinked',
        'record.archived',
        'record.restored',
        'record.file_added',
        'record.version_created',
    ];

    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordNumberGenerator $numbers,
        // Injected for its scoped() builder rather than for search: it is the
        // one place in the codebase that knows which Records the current Role
        // may see, and the Integration tab lists Records.
        private readonly RecordSearchService $search,
    ) {}

    public function tab(Request $request, string $tab): View
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        Gate::authorize('viewAny', RecordClassification::class);

        $data = match ($tab) {
            'classifications' => $this->classificationsData(),
            'sources' => $this->sourcesData(),
            'fields' => $this->fieldsData(),
            'numbering' => $this->numberingData(),
            'usage' => $this->usageData(),
            'integration' => $this->integrationData(),
        };

        return view('records.partials.setup.tab', [
            'tab' => $tab,
            'canManage' => Gate::allows('manageScheme'),
            ...$data,
        ]);
    }

    /**
     * The scheme as the two-level tree the modal shows.
     *
     * The same shape the Classifications page renders, so the two screens cannot
     * disagree about what the scheme is.
     *
     * @return array<string, mixed>
     */
    private function classificationsData(): array
    {
        $classifications = RecordClassification::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->with(['indexes' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')])
            ->orderBy('code')
            ->get();

        return [
            'classifications' => $classifications,
            // The same payload the Classifications page hands its browser
            // component, so one search implementation drives both screens.
            'scheme' => $classifications->map(fn (RecordClassification $classification): array => [
                'code' => $classification->code,
                'name' => $classification->name,
                'text' => $classification->searchText(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcesData(): array
    {
        return [
            'sources' => RecordSource::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->ordered()
                ->get(),
            'sourceTypes' => SourceType::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsData(): array
    {
        return [
            'fields' => RecordField::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->ordered()
                ->get(),
            'fieldTypes' => RecordFieldType::options(),
        ];
    }

    /**
     * Record Numbering: the settings, and a real example of what they produce.
     *
     * This tab was read-only until now. The reasoning behind that was sound but
     * answered the wrong question: it treated "Record Numbering" as though it
     * configured the Document Reference, and refused inputs on the grounds that
     * nothing behind them could change. The reference mock keeps the two
     * identifiers apart — a readable, ordered Record ID that a person configures,
     * and the 5-character Document Reference, which is system-controlled and
     * shown here purely to document it. So the inputs are real, and the
     * Document Reference below them is exactly as protected as it always was.
     *
     * @return array<string, mixed>
     */
    private function numberingData(): array
    {
        // Fresh, for the same reason the save does: filing advances the counter
        // through its own instance, so a held instance would show a number that
        // has already been issued.
        $company = $this->context->current()->fresh() ?? $this->context->current();

        return [
            'numbering' => [
                'prefix' => (string) ($company->record_prefix ?? 'REC'),
                'digits' => (int) ($company->record_sequence_digits ?? 6),
                'next' => (int) ($company->record_next_sequence ?? 1),
                'includeYear' => (bool) ($company->record_include_year ?? true),
            ],
            // preview() only formats — it never takes a number, so opening this
            // modal cannot advance the counter.
            'exampleRecordNumber' => $this->numbers->preview($company),
            // A real reference from this company, so the example is a genuine one
            // rather than an invented string. Null before anything is filed.
            'exampleReference' => Record::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->whereNotNull('document_reference')
                ->orderBy('filed_at')
                ->value('document_reference'),
            'referenceCount' => Record::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->whereNotNull('document_reference')
                ->count(),
        ];
    }

    /**
     * Usage & Storage, shown rather than edited.
     *
     * Both figures are summed from the company's own records and uploads, so they
     * cannot drift from what the dashboard reports. Archived records stay in the
     * sum because an archived record is still an official record and still bills.
     *
     * @return array<string, mixed>
     */
    private function usageData(): array
    {
        $tenantId = $this->context->tenantId();
        $companyId = $this->context->id();

        $records = Record::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId);

        $intake = RecordIntakeItem::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId);

        return [
            'recordUnitsUsed' => (int) (clone $records)->sum('record_units'),
            'recordUnitLimit' => $this->context->current()->record_unit_allocation,
            'storageBytesUsed' => (int) (clone $records)->sum('storage_bytes')
                + (int) (clone $intake)->sum('storage_bytes'),
            'storageLimitBytes' => $this->context->current()->storage_limit_bytes,
            'recordCount' => (clone $records)->count(),
            'intakeCount' => (clone $intake)->count(),
        ];
    }

    /**
     * Integration, shown rather than driven.
     *
     * Read-only on purpose. This tab documents how the Records engine connects to
     * the rest of ORDO; the actions that would exercise that connection belong to
     * the module integration phase, not to this module's setup screen. The link
     * counts are real, read from the polymorphic links table.
     *
     * @return array<string, mixed>
     */
    private function integrationData(): array
    {
        $tenantId = $this->context->tenantId();
        $companyId = $this->context->id();

        $links = RecordLink::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId);

        return [
            'linkCount' => (clone $links)->count(),
            'linkedRecords' => Record::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->whereHas('links')
                ->count(),
            'byModule' => (clone $links)
                ->selectRaw('linked_type, COUNT(*) as aggregate')
                ->groupBy('linked_type')
                ->orderByDesc('aggregate')
                ->limit(6)
                ->pluck('aggregate', 'linked_type'),
            ...$this->integrationPlanData(),
        ];
    }

    /**
     * The planned mapping, the wiring contract, and the recent events.
     *
     * Kept apart from the live figures above because the three have completely
     * different honesty. The link counts are measured. The mapping is a plan,
     * and is annotated with whether this company's scheme actually carries the
     * codes it names — which is the only thing on this tab an integrator can
     * act on. The wiring table names services that exist and services that do
     * not, and says which is which.
     *
     * @return array<string, mixed>
     */
    private function integrationPlanData(): array
    {
        $tenantId = $this->context->tenantId();
        $companyId = $this->context->id();

        // Scoped by tenant as well as company even though a company id is already
        // unique: the rest of the tab reads the same way, and a reader checking
        // this file should not have to work out which of the two is load-bearing.
        $classifications = RecordClassification::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->pluck('name', 'code');

        $indexes = RecordIndex::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->pluck('name', 'code');

        // Status is derived, never asserted. Nothing here is connected to a live
        // module — no ORDO module reaches the Records engine yet, and Corporate
        // Governance is explicitly a later phase — so the state the reference
        // mock labels "Ready" does not exist and the table says so rather than
        // borrowing it. What it reports instead is whether this company's own
        // scheme carries the codes the pairing names, which is the one thing on
        // this tab an integrator can act on, and the two states are toned
        // differently because they are not equally urgent: a missing code blocks
        // the row outright, a present one only asks for a review.
        $mappings = array_map(function (array $row) use ($classifications, $indexes): array {
            $inScheme = $classifications->has($row['classification']) && $indexes->has($row['index']);

            return [
                ...$row,
                'classificationName' => $classifications->get($row['classification']),
                'indexName' => $indexes->get($row['index']),
                'inScheme' => $inScheme,
                'status' => $inScheme ? 'Mapping Review' : 'Not in scheme',
                'statusTone' => $inScheme ? 'amber' : 'red',
                // Named in the tooltip so the pill itself can stay short.
                'statusHint' => $inScheme
                    ? sprintf('Classification %s and Index %s both exist in this scheme. The pairing still needs a human to confirm it before any module writes to it.', $row['classification'], $row['index'])
                    : sprintf('This scheme has no %s, so the pairing cannot be simulated until it is added under Records Classification.', $classifications->has($row['classification']) ? 'Index '.$row['index'] : 'Classification '.$row['classification']),
            ];
        }, GeneratedDocumentMappings::rows());

        return [
            'mappings' => $mappings,
            // The Global Record Picker travels with the tab rather than being
            // fetched on the button press. This fragment is already only fetched
            // when the tab is opened, so a second round trip would buy nothing,
            // and the list is capped well below anything worth paging.
            'pickerRecords' => $this->pickerRecords($this->search),
            'relationships' => LinkRelationship::options(),
            // Real events, newest first. The mock ships two hardcoded ones; the
            // trail already holds a better set, and now that Record IDs exist it
            // can name them the way a reader would recognise them.
            'usageEvents' => $this->usageEvents(),
            'wiring' => self::WIRING,
            // The company's own unregistered uploads — what the reference mock
            // calls "legacy attachments", without inventing fixture files.
            'unregistered' => RecordIntakeItem::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('status', IntakeStatus::Pending)
                ->with(['files' => fn ($query) => $query->orderBy('id')])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(fn (RecordIntakeItem $item): array => [
                    'id' => (string) $item->id,
                    'title' => (string) $item->title,
                    'name' => (string) ($item->files->first()?->original_name ?? $item->title),
                    'sizeBytes' => (int) $item->storage_bytes,
                    'fileCount' => $item->files->count(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The records the Global Record Picker offers.
     *
     * Read through the search service's scoped builder rather than off the model
     * directly, because that builder is where the confidentiality rule lives: a
     * Role that cannot open a confidential Record must not be handed its title
     * and reference through a list on a Setup tab it can reach. The tab is
     * available to anyone who can view Records, not only to administrators.
     *
     * Only filed records, too: an unclassified upload is not yet a Record and
     * cannot be the subject of a relationship. Capped, and the picker states the
     * cap so a reader is never left wondering why a record visible in the
     * register is missing from the list.
     */
    private function pickerRecords(RecordSearchService $search): Collection
    {
        return $search->scoped()
            ->whereNotNull('document_reference')
            ->orderByDesc('filed_at')
            ->orderBy('id')
            ->limit(25)
            ->get(['id', 'title', 'document_reference', 'record_number']);
    }

    /**
     * The most recent integration-relevant activity in this company's trail.
     *
     * Only the actions that represent the engine being used from outside — a
     * record filed, a link made or broken — rather than every entry, because a
     * re-read or an export is bookkeeping rather than integration.
     *
     * @return list<array<string, mixed>>
     */
    private function usageEvents(): array
    {
        $query = RecordActivityLog::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->whereIn('action', self::INTEGRATION_ACTIONS)
            ->with('record:id,record_number,document_reference')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // An entry names the Record it happened to, and its own description
        // usually quotes that Record's subject matter. So an entry on a
        // confidential Record is withheld whole from a Role that cannot open it,
        // rather than having its reference blanked and the text left standing.
        // Same rule as the register, applied here because this tab is about to
        // make it relevant: the Setup modal is open to anyone who can view
        // Records, not only to administrators.
        if (! Gate::allows('viewConfidential')) {
            $query->whereHas('record', fn (Builder $records) => $records->where('is_confidential', false));
        }

        return $query
            ->limit(5)
            ->get()
            ->map(fn (RecordActivityLog $entry): array => [
                // Formatted here rather than in the view: the trail is the
                // engine's own record, so the tab reads a value from it rather
                // than reaching into Carbon itself.
                'atLabel' => $entry->created_at?->format('n/j/Y, g:i A') ?? 'Unknown time',
                'action' => $entry->action,
                // Filing is the only action here that creates a billable record.
                'recordDelta' => $entry->action === 'record.filed' ? 1 : 0,
                'reference' => $entry->record?->record_number
                    ?? $entry->record?->document_reference,
                'note' => (string) $entry->description,
            ])
            ->values()
            ->all();
    }
}
