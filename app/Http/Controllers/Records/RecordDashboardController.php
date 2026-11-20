<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Services\Records\RecordCapacityService;
use App\Services\Records\RecordLookups;
use Illuminate\View\View;

/**
 * The Records Management dashboard.
 *
 * The figures are assembled here rather than in the view so that the view is one
 * layout pass and no aggregate is computed twice: counts() folds the completeness
 * and month-to-date figures into the count it already runs, and storage() folds
 * the file-role counts into the byte sum it already runs. usage() is handed both
 * of those rather than being allowed to re-read them.
 */
class RecordDashboardController extends Controller
{
    public function __construct(
        private readonly RecordCapacityService $capacity,
        private readonly RecordLookups $lookups,
    ) {}

    public function index(): View
    {
        $report = $this->capacity->report();

        return view('records.dashboard', [
            'title' => 'Records Management',
            'units' => $report['units'],
            'storage' => $report['storage'],
            'counts' => $report['counts'],
            'distribution' => $report['distribution'],
            'classifications' => $this->lookups->classifications(),
            'bySource' => $this->capacity->sourceDistribution(),
            'indexes' => $this->capacity->indexDistribution(),
            'activity' => $this->capacity->monthlyActivity(),
            'links' => $this->capacity->links(),
            'recentRecords' => $this->capacity->recentRecords(),
            'usage' => $this->capacity->usage($report['units'], $report['storage']),
        ]);
    }
}
