<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\HumanCapitalChangeRequest;
use App\Models\EmployeeRequest;
use App\Models\EmployeeRelation;
use App\Models\EmployeeSystemAccess;
use App\Models\HumanCapitalLog;
use App\Models\OfficialBusinessTrip;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Notifications\HumanCapitalWorkflowNotification;
use App\Support\HumanCapitalLogger;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminHumanCapitalDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeHumanCapitalAdmin();
        $activeView = (string) $request->query('view', 'approvals');

        $items = collect()
            ->merge($this->employeeRequestItems())
            ->merge($this->officialBusinessTripItems())
            ->merge($this->employeeRelationItems())
            ->merge($this->trainingAssignmentItems())
            ->merge($this->employeeSystemAccessItems())
            ->merge($this->awardItems())
            ->merge($this->changeRequestItems())
            ->sortByDesc('date_sort')
            ->values();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'module' => (string) $request->query('module', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];

        $filteredItems = $this->applyFilters($items, $filters)->values();

        $logFilters = [
            'search' => trim((string) $request->query('log_search', '')),
            'module' => (string) $request->query('log_module', 'all'),
            'action' => (string) $request->query('log_action', 'all'),
        ];

        $logsQuery = HumanCapitalLog::query()->latest('logged_at')->latest();

        if ($logFilters['search'] !== '') {
            $search = $logFilters['search'];
            $logsQuery->where(function ($query) use ($search) {
                $query->where('subject_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if ($logFilters['module'] !== 'all') {
            $logsQuery->where('module', $logFilters['module']);
        }

        if ($logFilters['action'] !== 'all') {
            $logsQuery->where('action', $logFilters['action']);
        }

        $logs = $logsQuery->paginate(15, ['*'], 'log_page')->withQueryString();

        $counts = [
            'pending' => $filteredItems->where('status', 'Pending Approval')->count(),
            'approved' => $filteredItems->where('status', 'Approved')->count(),
            'rejected' => $filteredItems->where('status', 'Rejected')->count(),
            'revision' => $filteredItems->where('status', 'Needs Revision')->count(),
            'total' => $filteredItems->count(),
        ];

        $perPage = 10;
        $currentPage = max((int) $request->query('page', 1), 1);

        $paginator = new LengthAwarePaginator(
            $filteredItems->forPage($currentPage, $perPage)->values(),
            $filteredItems->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.human-capital-dashboard', [
            'items' => $paginator,
            'pendingCount' => $counts['pending'],
            'approvedCount' => $counts['approved'],
            'rejectedCount' => $counts['rejected'],
            'revisionCount' => $counts['revision'],
            'totalCount' => $counts['total'],
            'filters' => $filters,
            'activeView' => in_array($activeView, ['approvals', 'logs'], true) ? $activeView : 'approvals',
            'logs' => $logs,
            'logFilters' => $logFilters,
            'logModuleOptions' => HumanCapitalLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'logActionOptions' => HumanCapitalLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'moduleOptions' => $items->pluck('module')->filter()->unique()->sort()->values(),
            'statusOptions' => collect(['Pending Approval', 'Approved', 'Rejected', 'Needs Revision']),
        ]);
    }

    public function approveEmployeeRequest(EmployeeRequest $employeeRequest)
    {
        $this->authorizeHumanCapitalAdmin();

        $employeeRequest->update([
            'status' => 'Approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'admin_note' => null,
        ]);

        $this->notifyEmployeeRequestOwner(
            $employeeRequest,
            'Employee request approved',
            'Your ' . $this->displayEmployeeRequestType($employeeRequest) . ' request has been approved.'
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Employee request approved successfully.');
    }

    public function rejectEmployeeRequest(Request $request, EmployeeRequest $employeeRequest)
    {
        $this->authorizeHumanCapitalAdmin();

        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $employeeRequest->update([
            'status' => 'Declined',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        $message = 'Your ' . $this->displayEmployeeRequestType($employeeRequest) . ' request has been rejected.';

        if ($request->filled('admin_note')) {
            $message .= ' Note: ' . $request->admin_note;
        }

        $this->notifyEmployeeRequestOwner(
            $employeeRequest,
            'Employee request rejected',
            $message
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Employee request rejected successfully.');
    }

    public function reviseEmployeeRequest(Request $request, EmployeeRequest $employeeRequest)
    {
        $this->authorizeHumanCapitalAdmin();

        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $employeeRequest->update([
            'status' => 'For Revision',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        $message = 'Your ' . $this->displayEmployeeRequestType($employeeRequest) . ' request needs revision.';

        if ($request->filled('admin_note')) {
            $message .= ' Note: ' . $request->admin_note;
        }

        $this->notifyEmployeeRequestOwner(
            $employeeRequest,
            'Employee request needs revision',
            $message
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Employee request sent back for revision.');
    }

    public function approveObf(OfficialBusinessTrip $officialBusinessTrip)
    {
        $this->authorizeHumanCapitalAdmin();

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                $officialBusinessTrip->status === 'Submitted',
                422,
                'Only submitted V3 Official Business requests can be approved.'
            );
        }

        $officialBusinessTrip->update([
            'status' => 'Approved',
        ]);

        $this->notifyUserById(
            $officialBusinessTrip->created_by,
            'Official Business Trip Form approved',
            'Your Official Business Trip Form has been approved.',
            route('human-capital.obf'),
            'Official Business Trip Form',
            $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->destination
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Official Business Trip request approved successfully.');
    }

    public function rejectObf(Request $request, OfficialBusinessTrip $officialBusinessTrip)
    {
        $this->authorizeHumanCapitalAdmin();

        if ((int) $officialBusinessTrip->workflow_version >= 3) {
            abort_unless(
                $officialBusinessTrip->status === 'Submitted',
                422,
                'Only submitted V3 Official Business requests can be rejected.'
            );
        }

        $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $officialBusinessTrip->update([
            'status' => 'Rejected',
            'remarks' => $request->remarks ?: $officialBusinessTrip->remarks,
        ]);

        $message = 'Your Official Business Trip Form has been rejected.';
        if ($request->filled('remarks')) {
            $message .= ' Note: ' . $request->remarks;
        }

        $this->notifyUserById(
            $officialBusinessTrip->created_by,
            'Official Business Trip Form rejected',
            $message,
            route('human-capital.obf'),
            'Official Business Trip Form',
            $officialBusinessTrip->ob_reference_no ?: $officialBusinessTrip->destination
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Official Business Trip request rejected successfully.');
    }

    public function approveEmployeeRelation(EmployeeRelation $employeeRelation)
    {
        $this->authorizeHumanCapitalAdmin();

        $employeeRelation->update([
            'status' => 'Resolved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyUserById(
            $employeeRelation->created_by,
            'Employee relations record resolved',
            'Your Employee Relations record has been resolved.',
            route('human-capital.employee-relations'),
            'Employee Relations',
            $employeeRelation->reference_no ?: $employeeRelation->subject
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Employee relations record marked as resolved.');
    }

    public function rejectEmployeeRelation(Request $request, EmployeeRelation $employeeRelation)
    {
        $this->authorizeHumanCapitalAdmin();

        $request->validate([
            'hr_remarks' => 'nullable|string|max:1000',
        ]);

        $employeeRelation->update([
            'status' => 'Closed',
            'hr_remarks' => $request->hr_remarks ?: $employeeRelation->hr_remarks,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $message = 'Your Employee Relations record has been closed.';
        if ($request->filled('hr_remarks')) {
            $message .= ' Note: ' . $request->hr_remarks;
        }

        $this->notifyUserById(
            $employeeRelation->created_by,
            'Employee relations record closed',
            $message,
            route('human-capital.employee-relations'),
            'Employee Relations',
            $employeeRelation->reference_no ?: $employeeRelation->subject
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Employee relations record closed successfully.');
    }

    public function completeTrainingAssignment(TrainingAssignment $trainingAssignment)
    {
        $this->authorizeHumanCapitalAdmin();

        $trainingAssignment->update([
            'status' => 'Completed',
            'completed_at' => $trainingAssignment->completed_at ?: now(),
        ]);

        $trainingAssignment->loadMissing(['employee.user', 'training']);
        $this->notifyUser(
            $trainingAssignment->employee?->user,
            'Training completion approved',
            'Your training completion has been approved.',
            route('human-capital.training'),
            'Training',
            $trainingAssignment->training?->title ?: 'Training Assignment #' . $trainingAssignment->id
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Training assignment marked as completed.');
    }

    public function issueTrainingCertificate(TrainingAssignment $trainingAssignment)
    {
        $this->authorizeHumanCapitalAdmin();

        $trainingAssignment->load(['training', 'employee']);

        if (! $trainingAssignment->completed_at && $trainingAssignment->status !== 'Completed') {
            return redirect()->route('admin.human-capital.dashboard')
                ->withErrors(['training' => 'Training must be completed before issuing a certificate.']);
        }

        if ($trainingAssignment->certificate_issued) {
            return redirect()->route('admin.human-capital.dashboard')
                ->with('success', 'Certificate was already issued for this training assignment.');
        }

        $code = 'CERT-' . strtoupper(uniqid());

        $trainingAssignment->update([
            'certificate_issued' => true,
            'certificate_issued_at' => now(),
            'certificate_code' => $code,
        ]);

        Award::create([
            'employee_id' => $trainingAssignment->employee_id,
            'training_id' => $trainingAssignment->training_id,
            'training_assignment_id' => $trainingAssignment->id,
            'certificate_code' => $code,
            'issued_at' => now(),
        ]);

        $this->notifyUser(
            $trainingAssignment->employee?->user,
            'Training certificate issued',
            'Your training certificate has been issued.',
            route('human-capital.awards'),
            'Awards',
            $trainingAssignment->training?->title ?: 'Certificate / Award'
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Training certificate issued successfully.');
    }

    public function approveEmployeeSystemAccess(EmployeeSystemAccess $systemAccess)
    {
        $this->authorizeHumanCapitalAdmin();

        if (($systemAccess->approval_status ?? 'Pending') === 'Approved') {
            return redirect()->route('admin.human-capital.dashboard')
                ->withErrors(['system_access' => 'This assigned platform record was already approved.']);
        }

        $systemAccess->update([
            'approval_status' => 'Approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        HumanCapitalLogger::log(request(), [
            'module' => 'System Access & Assigned Platforms',
            'action' => 'approved',
            'subject_type' => EmployeeSystemAccess::class,
            'subject_id' => $systemAccess->id,
            'subject_name' => $systemAccess->system_platform_name,
            'description' => 'Assigned platform documentation approved from the Human Capital admin dashboard.',
            'old_values' => ['approval_status' => 'Pending'],
            'new_values' => [
                'approval_status' => 'Approved',
                'approved_by' => Auth::id(),
            ],
        ]);

        $this->notifyUserById(
            $systemAccess->assigned_by ?: $systemAccess->created_by,
            'Assigned platform record approved',
            'Your assigned platform documentation was approved. Actual system permissions were not changed.',
            route('human-capital.employee-profile'),
            'System Access & Assigned Platforms',
            $systemAccess->system_platform_name
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Assigned platform record approved. Actual system permissions were not changed.');
    }

    public function approveChangeRequest(HumanCapitalChangeRequest $changeRequest)
    {
        $this->authorizeHumanCapitalAdmin();

        if ($changeRequest->status !== 'Pending Approval') {
            return redirect()->route('admin.human-capital.dashboard')
                ->withErrors(['change_request' => 'This change request was already reviewed.']);
        }

        $modelClass = $changeRequest->subject_type;
        $model = $modelClass::find($changeRequest->subject_id);

        if (! $model) {
            $changeRequest->update([
                'status' => 'Rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => 'Target record no longer exists.',
            ]);

            return redirect()->route('admin.human-capital.dashboard')
                ->withErrors(['change_request' => 'Target record no longer exists.']);
        }

        if ($changeRequest->action === 'delete') {
            $model->delete();
        } else {
            $model->update($changeRequest->new_values ?? []);
        }

        $changeRequest->update([
            'status' => 'Approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => null,
        ]);

        HumanCapitalLogger::log(request(), [
            'module' => $changeRequest->module,
            'action' => $changeRequest->action.'_approved',
            'subject_type' => $changeRequest->subject_type,
            'subject_id' => $changeRequest->subject_id,
            'subject_name' => $changeRequest->subject_name,
            'description' => str($changeRequest->action)->title().' request approved and applied.',
            'old_values' => $changeRequest->old_values,
            'new_values' => $changeRequest->new_values,
        ]);

        $this->notifyUserById(
            $changeRequest->requested_by,
            'Human Capital change request approved',
            'Your ' . $changeRequest->module . ' ' . $changeRequest->action . ' request was approved.',
            route('admin.human-capital.dashboard', ['view' => 'logs']),
            $changeRequest->module,
            $changeRequest->subject_name ?: ''
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Human Capital change request approved and applied.');
    }

    public function rejectChangeRequest(Request $request, HumanCapitalChangeRequest $changeRequest)
    {
        $this->authorizeHumanCapitalAdmin();

        $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($changeRequest->status !== 'Pending Approval') {
            return redirect()->route('admin.human-capital.dashboard')
                ->withErrors(['change_request' => 'This change request was already reviewed.']);
        }

        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $request->review_note,
        ]);

        $message = 'Your ' . $changeRequest->module . ' ' . $changeRequest->action . ' request was rejected.';
        if ($request->filled('review_note')) {
            $message .= ' Note: ' . $request->review_note;
        }

        $this->notifyUserById(
            $changeRequest->requested_by,
            'Human Capital change request rejected',
            $message,
            route('admin.human-capital.dashboard', ['view' => 'logs']),
            $changeRequest->module,
            $changeRequest->subject_name ?: ''
        );

        return redirect()->route('admin.human-capital.dashboard')
            ->with('success', 'Human Capital change request rejected.');
    }

    private function employeeRequestItems(): Collection
    {
        return EmployeeRequest::query()
            ->latest()
            ->get()
            ->map(function (EmployeeRequest $request): object {
                $status = $this->normalizeStatus((string) ($request->status ?? 'Pending'));

                return (object) [
                    'ref_no' => 'ER-'.$request->id,
                    'module' => 'Employee Requests',
                    'employee' => $request->employee_name ?: 'Unknown Employee',
                    'record_name' => $this->displayEmployeeRequestType($request),
                    'department' => $request->department ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($request->request_date ?: $request->created_at),
                    'approver' => $request->reviewed_by ? 'User #'.$request->reviewed_by : '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('human-capital.employee-requests.index'),
                    'approve_route' => $status === 'Pending Approval' ? route('admin.human-capital.employee-requests.approve', $request->id) : null,
                    'reject_route' => $status === 'Pending Approval' ? route('admin.human-capital.employee-requests.reject', $request->id) : null,
                    'revise_route' => $status === 'Pending Approval' ? route('admin.human-capital.employee-requests.revise', $request->id) : null,
                    'reject_note_name' => 'admin_note',
                    'revise_note_name' => 'admin_note',
                    'approve_label' => 'Approve',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($request->created_at),
                ];
            });
    }

    private function officialBusinessTripItems(): Collection
    {
        return OfficialBusinessTrip::query()
            ->latest()
            ->get()
            ->map(function (OfficialBusinessTrip $trip): object {
                $status = $this->normalizeStatus((string) ($trip->status ?? 'Pending'));

                return (object) [
                    'ref_no' => $trip->ob_reference_no ?: 'OBF-'.$trip->id,
                    'module' => 'Official Business Trip Form',
                    'employee' => $trip->employee_name ?: 'Unknown Employee',
                    'record_name' => $trip->destination ?: 'Official Business Trip',
                    'department' => $trip->department ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($trip->created_at),
                    'approver' => '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('human-capital.obf'),
                    'approve_route' => $status === 'Pending Approval' ? route('admin.human-capital.obf.approve', $trip->id) : null,
                    'reject_route' => $status === 'Pending Approval' ? route('admin.human-capital.obf.reject', $trip->id) : null,
                    'revise_route' => null,
                    'reject_note_name' => 'remarks',
                    'revise_note_name' => null,
                    'approve_label' => 'Approve',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($trip->created_at),
                ];
            });
    }

    private function employeeRelationItems(): Collection
    {
        return EmployeeRelation::query()
            ->latest()
            ->get()
            ->map(function (EmployeeRelation $relation): object {
                $status = $this->normalizeStatus((string) ($relation->status ?? 'Pending'));

                return (object) [
                    'ref_no' => $relation->reference_no ?: 'ERF-'.$relation->id,
                    'module' => 'Employee Relations',
                    'employee' => $relation->employee_name ?: 'Unknown Employee',
                    'record_name' => $relation->form_type_label ?: ($relation->subject ?: 'Employee Relations Record'),
                    'department' => $relation->department ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($relation->filed_at ?: $relation->created_at),
                    'approver' => $relation->reviewed_by ? 'User #'.$relation->reviewed_by : '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('human-capital.employee-relations'),
                    'approve_route' => $status === 'Pending Approval' ? route('admin.human-capital.employee-relations.approve', $relation->id) : null,
                    'reject_route' => $status === 'Pending Approval' ? route('admin.human-capital.employee-relations.reject', $relation->id) : null,
                    'revise_route' => null,
                    'reject_note_name' => 'hr_remarks',
                    'revise_note_name' => null,
                    'approve_label' => 'Resolve',
                    'reject_label' => 'Close',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($relation->created_at),
                ];
            });
    }

    private function trainingAssignmentItems(): Collection
    {
        return TrainingAssignment::query()
            ->with(['employee.department', 'training'])
            ->latest()
            ->get()
            ->map(function (TrainingAssignment $assignment): object {
                $status = $this->trainingDashboardStatus($assignment);
                $employee = $assignment->employee;
                $training = $assignment->training;
                $isCompleted = $assignment->completed_at || $assignment->status === 'Completed';
                $needsCertificate = $isCompleted && ! $assignment->certificate_issued;
                $needsCompletion = ! $isCompleted && $status === 'Pending Approval';

                return (object) [
                    'ref_no' => 'TRN-'.$assignment->id,
                    'module' => 'Training',
                    'employee' => $employee?->full_name ?: 'Unknown Employee',
                    'record_name' => $training?->title ?: 'Training Assignment',
                    'department' => $employee?->department?->department_name ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($assignment->start_date ?: $assignment->created_at),
                    'approver' => $assignment->assigned_by ? 'User #'.$assignment->assigned_by : '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('human-capital.training'),
                    'approve_route' => $needsCompletion
                        ? route('admin.human-capital.training-assignments.complete', $assignment->id)
                        : ($needsCertificate ? route('admin.human-capital.training-assignments.certificate', $assignment->id) : null),
                    'reject_route' => null,
                    'revise_route' => null,
                    'reject_note_name' => null,
                    'revise_note_name' => null,
                    'approve_label' => $needsCertificate ? 'Issue Certificate' : 'Mark Completed',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($assignment->created_at),
                ];
            });
    }

    private function awardItems(): Collection
    {
        return Award::query()
            ->with(['employee.department', 'training', 'assignment'])
            ->latest()
            ->get()
            ->map(function (Award $award): object {
                $employee = $award->employee;
                $training = $award->training;

                return (object) [
                    'ref_no' => $award->certificate_code ?: 'CERT-'.$award->id,
                    'module' => 'Awards',
                    'employee' => $employee?->full_name ?: 'Unknown Employee',
                    'record_name' => $training?->title ?: 'Certificate / Award',
                    'department' => $employee?->department?->department_name ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($award->issued_at ?: $award->created_at),
                    'approver' => '-',
                    'priority' => 'Low',
                    'status' => 'Approved',
                    'details_route' => route('human-capital.awards'),
                    'approve_route' => null,
                    'reject_route' => null,
                    'revise_route' => null,
                    'reject_note_name' => null,
                    'revise_note_name' => null,
                    'approve_label' => 'Approve',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($award->issued_at ?: $award->created_at),
                ];
            });
    }

    private function employeeSystemAccessItems(): Collection
    {
        return EmployeeSystemAccess::query()
            ->with(['employee.department', 'assignedBy', 'approvedBy'])
            ->latest()
            ->get()
            ->map(function (EmployeeSystemAccess $access): object {
                $employee = $access->employee;
                $status = $this->normalizeStatus((string) ($access->approval_status ?? 'Pending'));
                $account = collect([$access->account_type, $access->role_access_level, $access->username_email])
                    ->filter()
                    ->implode(' | ');

                return (object) [
                    'ref_no' => 'ESA-'.$access->id,
                    'module' => 'System Access & Assigned Platforms',
                    'employee' => $employee?->full_name ?: 'Unknown Employee',
                    'record_name' => trim($access->system_platform_name . ($account ? ' - '.$account : '')),
                    'department' => $employee?->department?->department_name ?: 'Human Capital',
                    'date_submitted' => $this->displayDate($access->created_at),
                    'approver' => $access->approvedBy?->name ?: '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('human-capital.employee-profile'),
                    'approve_route' => $status === 'Pending Approval' ? route('admin.human-capital.system-accesses.approve', $access->id) : null,
                    'reject_route' => null,
                    'revise_route' => null,
                    'reject_note_name' => null,
                    'revise_note_name' => null,
                    'approve_label' => 'Approve',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($access->created_at),
                ];
            });
    }

    private function changeRequestItems(): Collection
    {
        return HumanCapitalChangeRequest::query()
            ->latest('requested_at')
            ->get()
            ->map(function (HumanCapitalChangeRequest $request): object {
                $status = $this->normalizeStatus((string) $request->status);

                return (object) [
                    'ref_no' => 'HCR-'.$request->id,
                    'module' => $request->module,
                    'employee' => $request->requested_by_name ?: 'System User',
                    'record_name' => str($request->action)->title().' - '.($request->subject_name ?: class_basename($request->subject_type).' #'.$request->subject_id),
                    'department' => 'Human Capital',
                    'date_submitted' => $this->displayDate($request->requested_at ?: $request->created_at),
                    'approver' => $request->reviewed_by ? 'User #'.$request->reviewed_by : '-',
                    'priority' => $status === 'Pending Approval' ? 'High' : 'Low',
                    'status' => $status,
                    'details_route' => route('admin.human-capital.dashboard', ['view' => 'logs']),
                    'approve_route' => $status === 'Pending Approval' ? route('admin.human-capital.change-requests.approve', $request->id) : null,
                    'reject_route' => $status === 'Pending Approval' ? route('admin.human-capital.change-requests.reject', $request->id) : null,
                    'revise_route' => null,
                    'reject_note_name' => 'review_note',
                    'revise_note_name' => null,
                    'approve_label' => 'Apply',
                    'reject_label' => 'Reject',
                    'revise_label' => 'Revise',
                    'date_sort' => $this->sortTimestamp($request->requested_at ?: $request->created_at),
                ];
            });
    }

    private function trainingDashboardStatus(TrainingAssignment $assignment): string
    {
        $key = strtolower(trim((string) ($assignment->status ?? 'Pending')));

        if ($assignment->certificate_issued) {
            return 'Approved';
        }

        if ($assignment->completed_at || $key === 'completed') {
            return 'Pending Approval';
        }

        return match ($key) {
            'failed', 'cancelled', 'canceled', 'rejected', 'declined' => 'Rejected',
            'pending', 'scheduled', 'in progress', 'under review', 'for approval', 'pending approval' => 'Pending Approval',
            default => 'Pending Approval',
        };
    }

    private function applyFilters(Collection $items, array $filters): Collection
    {
        $search = strtolower($filters['search'] ?? '');
        $module = $filters['module'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        return $items->filter(function ($item) use ($search, $module, $status) {
            $matchesSearch = $search === '' || str_contains(strtolower(implode(' ', [
                $item->ref_no ?? '',
                $item->module ?? '',
                $item->employee ?? '',
                $item->record_name ?? '',
                $item->department ?? '',
                $item->status ?? '',
            ])), $search);

            $matchesModule = $module === 'all' || $item->module === $module;
            $matchesStatus = $status === 'all' || $item->status === $status;

            return $matchesSearch && $matchesModule && $matchesStatus;
        });
    }

    private function normalizeStatus(string $status): string
    {
        $key = strtolower(trim($status));

        return match ($key) {
            'pending', 'submitted', 'for approval', 'pending approval', 'under review', 'for mediation' => 'Pending Approval',
            'approved', 'accepted', 'completed', 'resolved' => 'Approved',
            'declined', 'rejected', 'denied', 'closed', 'cancelled', 'canceled' => 'Rejected',
            'for revision', 'needs revision', 'revision', 'sent back' => 'Needs Revision',
            default => $status !== '' ? $status : 'Pending Approval',
        };
    }

    private function displayDate($date): string
    {
        if (! $date) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('M d, Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function sortTimestamp($date): int
    {
        if (! $date) {
            return 0;
        }

        try {
            return Carbon::parse($date)->timestamp;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function notifyEmployeeRequestOwner(EmployeeRequest $employeeRequest, string $title, string $message): void
    {
        $owner = null;

        if (! empty($employeeRequest->user_id)) {
            $owner = User::find($employeeRequest->user_id);
        }

        if (! $owner) {
            return;
        }

        $this->notifyUser(
            $owner,
            $title,
            $message,
            route('human-capital.employee-requests.index'),
            'Employee Requests',
            $this->displayEmployeeRequestType($employeeRequest)
        );
    }

    private function displayEmployeeRequestType(EmployeeRequest $employeeRequest): string
    {
        if ($employeeRequest->request_type === 'Other' && $employeeRequest->request_type_other) {
            return $employeeRequest->request_type_other;
        }

        return $employeeRequest->request_type ?: 'Employee Request';
    }

    private function notifyUserById($userId, string $title, string $message, ?string $url, string $module, string $recordTitle = ''): void
    {
        if (empty($userId)) {
            return;
        }

        $this->notifyUser(User::find($userId), $title, $message, $url, $module, $recordTitle);
    }

    private function notifyUser(?User $user, string $title, string $message, ?string $url, string $module, string $recordTitle = ''): void
    {
        if (! $user) {
            return;
        }

        $user->notify(new HumanCapitalWorkflowNotification(
            $title,
            $message,
            $url,
            $module,
            $recordTitle,
            Auth::user()?->name ?? Auth::user()?->email ?? ''
        ));
    }

    private function authorizeHumanCapitalAdmin(): void
    {
        $user = Auth::user();

        if (! $user || (! $user->isAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Only admins can access the Human Capital admin dashboard.');
        }
    }
}
