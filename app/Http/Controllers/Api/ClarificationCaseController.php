<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Requests\ClarificationCase\CreateClarificationCaseRequest;
use App\Http\Resources\ClarificationCaseResource;
use App\Models\ClarificationCase;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClarificationCaseController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = ClarificationCase::query();
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-opened_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['opened_at', 'created_at', 'updated_at', 'severity', 'status', 'case_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'opened_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = ClarificationCaseResource::collection($paginator->items());

        $lastUpdated = ClarificationCase::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $this->summary(), $lastUpdated, 'Clarification cases retrieved');
    }

    public function show($id)
    {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error('Clarification case not found', 'CLARIFICATION_NOT_FOUND', 404);
        }

        return $this->success(new ClarificationCaseResource($case), 'Clarification case retrieved');
    }

    public function store(CreateClarificationCaseRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();
        $severity = $data['severity'] ?? ClarificationSeverity::NORMAL;

        return DB::transaction(function () use ($data, $severity, $audit, $events) {
            // TODO: re-enable when auth lands — populate opened_by_user_id / created_by_user_id from request()->user().
            $case = ClarificationCase::create($data);

            $audit->record(
                $case,
                AuditAction::CLARIFICATION_CREATED,
                null,
                $audit->snapshotModel($case),
                $case->description,
                $case->reason_code
            );

            $eventSeverity = $severity === ClarificationSeverity::CRITICAL
                ? EventSeverity::CRITICAL
                : EventSeverity::WARNING;

            $events->record(
                'clarification.created',
                $case,
                "Clarification {$case->case_no} opened ({$case->category})",
                [
                    'category' => $case->category,
                    'entity_type' => $case->entity_type,
                    'entity_id' => $case->entity_id,
                    'severity' => $severity,
                    'is_blocking' => (bool) $case->is_blocking,
                ],
                EventCategory::OPERATIONS,
                $eventSeverity
            );

            return ApiResponse::success(new ClarificationCaseResource($case->fresh()), 'Clarification case created', 201);
        });
    }

    // ---------- helpers ----------

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('case_no', 'like', $search)
                    ->orWhere('entity_label', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['owner_role'])) {
            $query->where('owner_role', $filters['owner_role']);
        }

        if (array_key_exists('is_blocking', $filters) && $filters['is_blocking'] !== null && $filters['is_blocking'] !== '') {
            $query->where('is_blocking', filter_var($filters['is_blocking'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function summary(): array
    {
        $base = ClarificationCase::query();

        $totalOpen = (clone $base)
            ->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW])
            ->count();

        $criticalOpen = (clone $base)
            ->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW])
            ->where('severity', ClarificationSeverity::CRITICAL)
            ->count();

        $blockingOpen = (clone $base)
            ->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW])
            ->where('is_blocking', true)
            ->count();

        $byOwnerRole = (clone $base)
            ->whereIn('status', [ClarificationStatus::OPEN, ClarificationStatus::IN_REVIEW])
            ->selectRaw('owner_role, COUNT(*) as c')
            ->groupBy('owner_role')
            ->pluck('c', 'owner_role')
            ->toArray();

        return [
            'totalOpen' => $totalOpen,
            'criticalOpen' => $criticalOpen,
            'blockingOpen' => $blockingOpen,
            'byOwnerRole' => $byOwnerRole,
        ];
    }
}
