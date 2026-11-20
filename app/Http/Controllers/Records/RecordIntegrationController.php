<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimulateGeneratedDocumentRequest;
use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Services\CompanyContext;
use App\Services\Records\RecordFilingService;
use App\Services\Records\RecordIntakeService;
use App\Support\GeneratedDocumentMappings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * "Simulate Generated Document", from the Integration tab.
 *
 * Files a real record down the ordinary intake → filing path, using one of the
 * pairings the tab's table already shows. It exists so the Integration tab's
 * claims can be checked rather than taken on trust: that a module can hand a
 * finished document to the engine, that the engine issues a Record ID and a
 * Document Reference, and that the whole thing costs exactly one Record Unit.
 *
 * It is a real filing, and it says so. The button is labelled with the cost, and
 * a pairing whose Classification or Index is not in this company's scheme is
 * refused by name rather than silently filed somewhere else — the table marks
 * those rows "Not in scheme" for exactly this reason.
 */
class RecordIntegrationController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordIntakeService $intake,
        private readonly RecordFilingService $filing,
    ) {}

    public function simulate(SimulateGeneratedDocumentRequest $request): RedirectResponse
    {
        $row = GeneratedDocumentMappings::resolve((int) $request->input('mapping'));

        if ($row === null) {
            return back()
                ->with('setup_tab', 'integration')
                ->with('error', 'That mapping is not one this tab offers.');
        }

        $tenantId = (string) $this->context->tenantId();
        $companyId = (string) $this->context->id();

        $classification = RecordClassification::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $row['classification'])
            ->first();

        $index = RecordIndex::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $row['index'])
            ->first();

        if (! $classification instanceof RecordClassification || ! $index instanceof RecordIndex) {
            return back()
                ->with('setup_tab', 'integration')
                ->with('error', sprintf(
                    '%s maps to %s / %s, and this company does not carry %s in its scheme. Add %s under Records Classification before simulating it.',
                    $row['document'],
                    $row['classification'],
                    $row['index'],
                    $classification === null ? 'Classification '.$row['classification'] : 'Index '.$row['index'],
                    $classification === null ? $row['classification'] : $row['index'],
                ));
        }

        $item = $this->intake->createIntakeItem(
            [$this->sample($row)],
            [
                'title' => $row['document'],
                'source' => $row['module'],
            ],
        );

        $record = $this->filing->file($item, [
            'record_classification_id' => $classification->id,
            'record_index_id' => $index->id,
            'title' => $row['document'],
            'source' => $row['module'],
        ], Auth::user());

        return back()
            ->with('setup_tab', 'integration')
            ->with('status', sprintf(
                'Simulated %s from %s and filed it as %s (%s). One Record Unit was consumed.',
                $row['document'],
                $row['module'],
                (string) $record->record_number,
                (string) $record->document_reference,
            ));
    }

    /**
     * A small, real PDF standing in for the module's output.
     *
     * Generated rather than stored as a fixture so the sample is honest about
     * what it is, and so nothing pretends to be a Board Resolution that is not
     * one. It is a real PDF so the preview pane can actually show it.
     */
    private function sample(array $row): UploadedFile
    {
        $body = "%PDF-1.4\n"
            .sprintf("1 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n")
            ."2 0 obj<</Type/Pages/Kids[1 0 R]/Count 1>>endobj\n"
            .sprintf("3 0 obj<</Length 0>>stream\n\nendstream endobj\n")
            ."trailer<</Root 2 0 R/Size 4>>\n%%EOF\n";

        $path = tempnam(sys_get_temp_dir(), 'ordo-simulated-');
        file_put_contents($path, $body);

        return new UploadedFile(
            $path,
            GeneratedDocumentMappings::sampleFilename($row),
            'application/pdf',
            null,
            true,
        );
    }
}
