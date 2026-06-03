<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SafetyTraining\MarkModuleCompletedRequest;
use App\Http\Requests\SafetyTraining\SubmitExamRequest;
use App\Http\Resources\TrainingExamResultResource;
use App\Http\Resources\TrainingModuleResource;
use App\Models\Driver;
use App\Models\TerminalSession;
use App\Services\SafetyTraining\SafetyTrainingCatalog;
use App\Services\SafetyTraining\SafetyTrainingService;

/**
 * Driver Terminal Safety Training (V6 §7).
 *
 * All endpoints live under the public `/api/terminal/...` prefix. The 3
 * GET catalog endpoints take no session id; the 2 POST write endpoints
 * take the driver's `terminal_sessions.id` in the URL as the credential —
 * same pattern as `POST /api/terminal/session/{id}/logout`.
 */
class SafetyTrainingController extends ApiController
{
    public function __construct(protected SafetyTrainingService $service) {}

    /* ============================================================
     * Catalog (no session required)
     * ============================================================ */

    /** V6 §7.3 — the 4-module catalog. */
    public function modules()
    {
        $rows = array_map(
            static fn (array $m) => (new TrainingModuleResource($m, TrainingModuleResource::SHAPE_LIST))->toArray(request()),
            SafetyTrainingCatalog::modules()
        );

        return $this->success([
            'modules' => $rows,
            'orderedModuleIds' => SafetyTrainingCatalog::moduleIds(),
            'passThreshold' => SafetyTrainingCatalog::PASS_THRESHOLD,
            'examTotal' => SafetyTrainingCatalog::EXAM_TOTAL,
        ], __('terminal.messages.modules_retrieved'));
    }

    /** V6 §7.5 — module detail page payload. */
    public function module(string $moduleId)
    {
        $module = SafetyTrainingCatalog::module($moduleId);
        if (! $module) {
            return $this->error(
                "Module '{$moduleId}' not found",
                'TRAINING_MODULE_NOT_FOUND',
                404,
                ['allowedModules' => SafetyTrainingCatalog::moduleIds()]
            );
        }

        return $this->success(
            (new TrainingModuleResource($module, TrainingModuleResource::SHAPE_DETAIL))->toArray(request()),
            __('terminal.messages.module_retrieved')
        );
    }

    /**
     * V6 §7.6 — the 5 questions WITHOUT correct answers. The answer key
     * is private to SafetyTrainingCatalog::EXAM_ANSWER_KEY and grading
     * happens server-side in SafetyTrainingService::recordExamSubmission().
     */
    public function exam()
    {
        return $this->success([
            'questions' => SafetyTrainingCatalog::examQuestions(false),
            'total' => SafetyTrainingCatalog::EXAM_TOTAL,
            'passThreshold' => SafetyTrainingCatalog::PASS_THRESHOLD,
        ], __('terminal.messages.exam_retrieved'));
    }

    /* ============================================================
     * Session-scoped writes (session id is the credential)
     * ============================================================ */

    public function markModuleCompleted(MarkModuleCompletedRequest $request, string $sessionId)
    {
        $session = TerminalSession::find($sessionId);
        if (! $session) {
            return $this->error(__('terminal.messages.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }
        if (! $session->driver_id) {
            return $this->error(
                __('terminal.messages.session_no_driver_progress'),
                'SESSION_HAS_NO_DRIVER',
                409
            );
        }

        $driver = Driver::find($session->driver_id);
        if (! $driver) {
            return $this->error(__('terminal.messages.driver_not_found'), 'DRIVER_NOT_FOUND', 404);
        }

        $moduleId = $request->validated()['moduleId'];

        $payload = $this->service->recordModuleCompleted($driver, $session, $moduleId);

        return $this->success($payload, __('terminal.messages.training_recorded'));
    }

    public function submitExam(SubmitExamRequest $request, string $sessionId)
    {
        $session = TerminalSession::find($sessionId);
        if (! $session) {
            return $this->error(__('terminal.messages.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }
        if (! $session->driver_id) {
            return $this->error(
                __('terminal.messages.session_no_driver'),
                'SESSION_HAS_NO_DRIVER',
                409
            );
        }

        $driver = Driver::find($session->driver_id);
        if (! $driver) {
            return $this->error(__('terminal.messages.driver_not_found'), 'DRIVER_NOT_FOUND', 404);
        }

        $answers = $request->validated()['answers'];

        $result = $this->service->recordExamSubmission($driver, $session, $answers);

        return $this->success(
            (new TrainingExamResultResource($result))->toArray(request()),
            $result['passed'] ? __('terminal.messages.exam_passed') : __('terminal.messages.exam_failed')
        );
    }
}
