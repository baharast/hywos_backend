<?php

namespace App\Services\SafetyTraining;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Models\Driver;
use App\Models\DriverTrainingExamResult;
use App\Models\DriverTrainingProgress;
use App\Models\TerminalSession;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates V6 §7 progress + exam writes.
 *
 * Responsibilities:
 *   - upsert per-driver, per-module progress (idempotent on repeat clicks)
 *   - record every exam attempt as append-only history
 *   - on a passing exam, flip `drivers.training_status = valid` and stamp
 *     `training_valid_until = now()+1 year`
 *   - emit one audit + one event row per write
 *
 * The controller passes the resolved `TerminalSession` so this service can
 * stamp `terminal_session_id` and `correlation_id` on every row without
 * re-fetching anything.
 */
class SafetyTrainingService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
    ) {}

    /**
     * Idempotent "I confirm this module" upsert.
     *
     * @return array{
     *   driverId:string,
     *   moduleId:string,
     *   completedAt:string,
     *   allModulesComplete:bool
     * }
     */
    public function recordModuleCompleted(
        Driver $driver,
        TerminalSession $session,
        string $moduleId
    ): array {
        return DB::transaction(function () use ($driver, $session, $moduleId) {
            $correlationId = $this->correlationId();

            /** @var DriverTrainingProgress $row */
            $row = DriverTrainingProgress::firstOrCreate(
                [
                    'driver_id' => $driver->id,
                    'module_id' => $moduleId,
                ],
                [
                    'completed_at' => now(),
                    'terminal_session_id' => $session->id,
                    'correlation_id' => $correlationId,
                ]
            );

            $allDone = $this->allModulesCompleteFor($driver->id);

            // Audit + event written every time (including idempotent
            // re-clicks) so the trail captures driver re-confirmation.
            $this->audit->record(
                $row,
                AuditAction::TERMINAL_TRAINING_MODULE_COMPLETED,
                null,
                [
                    'driver_id' => $driver->id,
                    'module_id' => $moduleId,
                    'terminal_session_id' => $session->id,
                    'all_modules_complete' => $allDone,
                ],
                'Module confirmed by driver',
                null
            );
            $this->events->record(
                'terminal.training.module_completed',
                $row,
                "Driver {$driver->driver_code} confirmed module {$moduleId}",
                [
                    'driver_id' => $driver->id,
                    'driver_code' => $driver->driver_code,
                    'module_id' => $moduleId,
                    'terminal_session_id' => $session->id,
                    'all_modules_complete' => $allDone,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return [
                'driverId' => $driver->id,
                'moduleId' => $moduleId,
                'completedAt' => $row->completed_at?->toIso8601String() ?? now()->toIso8601String(),
                'allModulesComplete' => $allDone,
            ];
        });
    }

    /**
     * Grade + record + (on pass) update driver.training_status.
     *
     * @param array<int, array{questionId:string, choice:string}> $answers
     * @return array{
     *   driverId:string,
     *   score:int,
     *   total:int,
     *   passed:bool,
     *   trainingValid:bool,
     *   nextRoute:string,
     *   examResultId:string
     * }
     */
    public function recordExamSubmission(
        Driver $driver,
        TerminalSession $session,
        array $answers
    ): array {
        $graded = SafetyTrainingCatalog::gradeExam($answers);

        return DB::transaction(function () use ($driver, $session, $answers, $graded) {
            $correlationId = $this->correlationId();

            /** @var DriverTrainingExamResult $result */
            $result = DriverTrainingExamResult::create([
                'driver_id' => $driver->id,
                'score' => $graded['score'],
                'total' => $graded['total'],
                'passed' => $graded['passed'],
                'answers' => $answers,
                'terminal_session_id' => $session->id,
                'correlation_id' => $correlationId,
            ]);

            $trainingValid = (bool) $graded['passed'];

            if ($graded['passed']) {
                $driver->forceFill([
                    'training_status' => 'valid',
                    'training_valid_until' => Carbon::now()->addYear()->toDateString(),
                ])->save();
            }

            // A single SUBMITTED audit row records the attempt; PASSED /
            // FAILED carries the outcome separately so an auditor can
            // count pass-rate without re-reading the score.
            $this->audit->record(
                $result,
                AuditAction::TERMINAL_TRAINING_EXAM_SUBMITTED,
                null,
                [
                    'driver_id' => $driver->id,
                    'score' => $graded['score'],
                    'total' => $graded['total'],
                    'passed' => $graded['passed'],
                    'terminal_session_id' => $session->id,
                ],
                'Driver submitted safety knowledge check',
                null
            );

            $outcomeAction = $graded['passed']
                ? AuditAction::TERMINAL_TRAINING_EXAM_PASSED
                : AuditAction::TERMINAL_TRAINING_EXAM_FAILED;
            $outcomeEvent = $graded['passed']
                ? 'terminal.training.exam_passed'
                : 'terminal.training.exam_failed';
            $outcomeSeverity = $graded['passed']
                ? EventSeverity::INFO
                : EventSeverity::WARNING;

            $this->audit->record(
                $result,
                $outcomeAction,
                null,
                [
                    'driver_id' => $driver->id,
                    'score' => $graded['score'],
                    'total' => $graded['total'],
                    'training_valid_until' => $graded['passed'] ? $driver->training_valid_until : null,
                ],
                $graded['passed'] ? 'Driver passed safety knowledge check' : 'Driver failed safety knowledge check',
                null
            );
            $this->events->record(
                $outcomeEvent,
                $result,
                "Driver {$driver->driver_code} {$this->outcomeLabel($graded['passed'])} (score {$graded['score']}/{$graded['total']})",
                [
                    'driver_id' => $driver->id,
                    'driver_code' => $driver->driver_code,
                    'score' => $graded['score'],
                    'total' => $graded['total'],
                    'passed' => $graded['passed'],
                    'terminal_session_id' => $session->id,
                ],
                EventCategory::OPERATIONS,
                $outcomeSeverity
            );

            return [
                'driverId' => $driver->id,
                'score' => $graded['score'],
                'total' => $graded['total'],
                'passed' => $graded['passed'],
                'trainingValid' => $trainingValid,
                'nextRoute' => $this->resolveNextRoute($session),
                'examResultId' => $result->id,
            ];
        });
    }

    /* =====================================================================
     * Read helpers
     * ===================================================================== */

    /**
     * Used by the FE overview page to render "Module N of 4 / NN %".
     *
     * @return array{
     *   completedModuleIds:array<int,string>,
     *   allModulesComplete:bool,
     *   examPassed:bool,
     *   trainingValid:bool
     * }
     */
    public function progressFor(Driver $driver): array
    {
        $completed = DriverTrainingProgress::query()
            ->where('driver_id', $driver->id)
            ->pluck('module_id')
            ->all();

        $lastExam = DriverTrainingExamResult::query()
            ->where('driver_id', $driver->id)
            ->orderByDesc('submitted_at')
            ->first();

        $trainingValid = $driver->training_status === 'valid'
            && (
                ! $driver->training_valid_until
                || Carbon::parse($driver->training_valid_until)->isFuture()
                || Carbon::parse($driver->training_valid_until)->isToday()
            );

        return [
            'completedModuleIds' => array_values(array_intersect(
                SafetyTrainingCatalog::moduleIds(),
                $completed
            )),
            'allModulesComplete' => $this->allModulesCompleteFor($driver->id),
            'examPassed' => $lastExam ? (bool) $lastExam->passed : false,
            'trainingValid' => $trainingValid,
        ];
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    protected function allModulesCompleteFor(string $driverId): bool
    {
        $count = DriverTrainingProgress::query()
            ->where('driver_id', $driverId)
            ->whereIn('module_id', SafetyTrainingCatalog::moduleIds())
            ->distinct('module_id')
            ->count('module_id');

        return $count >= count(SafetyTrainingCatalog::moduleIds());
    }

    protected function correlationId(): ?string
    {
        $request = request();
        if (! $request) {
            return null;
        }
        $cid = $request->attributes->get(CorrelationIdMiddleware::ATTRIBUTE);
        return $cid ? (string) $cid : null;
    }

    protected function outcomeLabel(bool $passed): string
    {
        return $passed ? 'passed' : 'failed';
    }

    /**
     * The kiosk caches `pendingNextRoute` in session.notes (a free-form
     * JSON column on TerminalSession). We attempt to parse it; if anything
     * goes wrong we fall back to `/terminal/login` per V6 §7.10.
     */
    protected function resolveNextRoute(TerminalSession $session): string
    {
        $notes = $session->notes;
        if (! is_string($notes) || trim($notes) === '') {
            return '/terminal/login';
        }

        $decoded = json_decode($notes, true);
        if (is_array($decoded) && isset($decoded['pendingNextRoute']) && is_string($decoded['pendingNextRoute'])) {
            $candidate = trim($decoded['pendingNextRoute']);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return '/terminal/login';
    }
}
