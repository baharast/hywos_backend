<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Alarm\AssignAlarmRequest;
use App\Http\Requests\Alarm\CloseAlarmRequest;
use App\Http\Requests\Alarm\ResolveAlarmRequest;
use App\Http\Resources\ActiveAlarmResource;
use App\Models\Alarm;
use App\Models\AuditLog;
use App\Services\ApiResponse;
use App\Services\AlarmsEvents\ActiveAlarmsService;
use Illuminate\Http\Request;

/**
 * V1 §6 — Active Alarms.
 *
 * Live operational queue + the 5 workflow actions allowed by V1 §6.9:
 * acknowledge / assign / mark-in-progress / resolve / close. All
 * workflow records only — per V1 §6.10 no physical resets, no PLC
 * writes, no remote gate open, no force release, no force success.
 */
class ActiveAlarmController extends ApiController
{
    public function __construct(protected ActiveAlarmsService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = ActiveAlarmResource::collection($paginator->items());

        $lastUpdated = Alarm::query()->max('last_seen_at')
            ?? Alarm::query()->max('first_seen_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            'Active alarms retrieved'
        );
    }

    public function show(string $id)
    {
        $alarm = Alarm::query()->find($id);
        if (! $alarm) {
            return $this->error('Alarm not found', 'ALARM_NOT_FOUND', 404);
        }

        $auditRows = AuditLog::query()
            ->where('entity_type', $alarm->getMorphClass())
            ->where('entity_id', $alarm->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'action' => $r->action,
                'actorUserId' => $r->actor_user_id,
                'actorName' => $r->actor_name,
                'reason' => $r->reason,
                'createdAt' => $r->created_at?->toIso8601String(),
            ])
            ->all();

        $resource = (new ActiveAlarmResource($alarm))
            ->additional([
                'technicalPayload' => $alarm->technical_payload,
                'auditRows' => $auditRows,
            ]);

        return $this->success($resource, 'Alarm detail retrieved');
    }

    public function acknowledge(string $id)
    {
        $alarm = $this->findOr404($id);
        if (! ($alarm instanceof Alarm)) return $alarm;

        try {
            $alarm = $this->service->acknowledge($alarm);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'ALARM_INVALID_STATE', 409);
        }

        return $this->success(new ActiveAlarmResource($alarm), 'Alarm acknowledged');
    }

    public function assign(AssignAlarmRequest $request, string $id)
    {
        $alarm = $this->findOr404($id);
        if (! ($alarm instanceof Alarm)) return $alarm;

        try {
            $alarm = $this->service->assign($alarm, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'ALARM_INVALID_STATE', 409);
        }

        return $this->success(new ActiveAlarmResource($alarm), 'Alarm assigned');
    }

    public function markInProgress(string $id)
    {
        $alarm = $this->findOr404($id);
        if (! ($alarm instanceof Alarm)) return $alarm;

        try {
            $alarm = $this->service->markInProgress($alarm);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'ALARM_INVALID_STATE', 409);
        }

        return $this->success(new ActiveAlarmResource($alarm), 'Alarm marked in progress');
    }

    public function resolve(ResolveAlarmRequest $request, string $id)
    {
        $alarm = $this->findOr404($id);
        if (! ($alarm instanceof Alarm)) return $alarm;

        try {
            $alarm = $this->service->resolve($alarm, $request->validated());
        } catch (\DomainException $e) {
            $code = str_contains($e->getMessage(), 'resolution reason')
                ? 'ALARM_RESOLUTION_REASON_REQUIRED'
                : 'ALARM_INVALID_STATE';
            $status = $code === 'ALARM_RESOLUTION_REASON_REQUIRED' ? 422 : 409;
            return $this->error($e->getMessage(), $code, $status);
        }

        return $this->success(new ActiveAlarmResource($alarm), 'Alarm resolved (pending close)');
    }

    public function close(CloseAlarmRequest $request, string $id)
    {
        $alarm = $this->findOr404($id);
        if (! ($alarm instanceof Alarm)) return $alarm;

        try {
            $alarm = $this->service->close($alarm, $request->validated());
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'acknowledged before close')) {
                return $this->error($msg, 'ALARM_NOT_ACKNOWLEDGED', 422);
            }
            if (str_contains($msg, 'resolution reason')) {
                return $this->error($msg, 'ALARM_RESOLUTION_REASON_REQUIRED', 422);
            }
            return $this->error($msg, 'ALARM_INVALID_STATE', 409);
        }

        return $this->success(new ActiveAlarmResource($alarm), 'Alarm closed');
    }

    protected function findOr404(string $id)
    {
        $alarm = Alarm::query()->find($id);
        if (! $alarm) {
            return $this->error('Alarm not found', 'ALARM_NOT_FOUND', 404);
        }
        return $alarm;
    }
}
