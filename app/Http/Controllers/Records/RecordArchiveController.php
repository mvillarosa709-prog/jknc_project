<?php

namespace App\Http\Controllers\Records;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Services\Records\RecordSearchService;
use App\Services\Records\RecordService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The archive of records this company has retired from the active register.
 *
 * Archiving is a lifecycle transition, not a deletion, so everything here still
 * points at live rows: the Document Reference, the Record Unit and the audit
 * trail are all intact. Only records the current role may view are listed, which
 * means a confidential record stays out of the archive for anyone who could not
 * see it in the register either.
 */
class RecordArchiveController extends Controller
{
    public function __construct(
        private readonly RecordService $records,
        private readonly RecordSearchService $search,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Record::class);

        return view('records.archive', [
            'title' => 'Archive',
            'archived' => $this->archivedQuery()->get(),
        ]);
    }

    /**
     * Archived records for the active company, newest archived first.
     *
     * The search service owns company scoping and the confidential rule, so this
     * reuses it rather than restating both: a query written here could easily
     * widen past the tenant boundary or leak confidential rows.
     */
    private function archivedQuery(): Builder
    {
        return $this->search->scoped()
            ->where('status', RecordStatus::Archived)
            ->with(['classification', 'index'])
            ->orderByDesc('archived_at')
            ->latest('id');
    }

    public function restore(Record $record): RedirectResponse
    {
        // Restoring is the inverse of archiving, so it follows the same rule.
        Gate::authorize('archive', $record);

        $restored = $this->records->restore($record, Auth::user());

        return redirect()
            ->route('records.archive.index')
            ->with('status', sprintf(
                'Record %s was restored to the active register. It keeps its Document Reference and Record Unit.',
                (string) $restored->document_reference,
            ));
    }
}
