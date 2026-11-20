<?php

namespace App\Http\Controllers\RecordIntake;

use App\Enums\CaptureSource;
use App\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntakeRequest;
use App\Models\RecordIntakeItem;
use App\Services\CompanyContext;
use App\Services\Records\RecordIntakeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function __construct(private readonly RecordIntakeService $intake) {}

    public function create(Request $request, CompanyContext $context): View
    {
        $itemId = $request->query('intake');

        return view('records.intake.upload', [
            'title' => 'Unclassified Records',
            'unclassifiedCount' => $this->openItems($context)->count(),
            'intakeItems' => $this->openItems($context)
                ->with(['files' => fn ($query) => $query->currentMasterFirst()])
                ->latest('created_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'intake' => $itemId
                ? $this->openItems($context)->with(['files' => fn ($query) => $query->currentMasterFirst()])->find($itemId)
                : null,
        ]);
    }

    /**
     * Unclassified records are intake items that have not been filed or discarded yet.
     */
    private function openItems(CompanyContext $context): Builder
    {
        return RecordIntakeItem::query()
            ->where('tenant_id', $context->tenantId())
            ->where('company_id', $context->id())
            ->whereNotIn('status', [IntakeStatus::Filed, IntakeStatus::Discarded]);
    }

    public function store(StoreIntakeRequest $request): RedirectResponse
    {
        $item = $this->intake->createIntakeItem(
            $request->file('files', []),
            [
                'title' => $request->input('title'),
                'source' => $request->input('source'),
                'document_date' => $request->input('document_date'),
                'external_reference_number' => $request->input('external_reference_number'),
                'uploaded_by' => Auth::id(),
            ],
            $this->resolveCaptureSource($request),
        );

        return redirect()
            ->route('records.intake.upload', ['intake' => $item->id])
            ->with('status', sprintf(
                '%d file(s) added to Unclassified Records. Intake consumes storage only; no Record Unit is used until the document is filed.',
                $item->files()->count(),
            ));
    }

    private function resolveCaptureSource(StoreIntakeRequest $request): CaptureSource
    {
        $declared = $request->input('capture_source');

        if (is_string($declared) && $declared !== '') {
            return CaptureSource::tryFrom($declared)
                ?? CaptureSource::forCount(count($request->file('files', [])));
        }

        return CaptureSource::forCount(count($request->file('files', [])));
    }
}
