<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V6 §7.8 — the response shape for `POST .../training/exam-submitted`.
 *
 * The resource is fed a plain array from SafetyTrainingService::record-
 * ExamSubmission(); we project it as-is with camelCase keys.
 */
class TrainingExamResultResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var array $r */
        $r = $this->resource;

        return [
            'examResultId' => $r['examResultId'] ?? null,
            'driverId' => $r['driverId'] ?? null,
            'score' => $r['score'] ?? null,
            'total' => $r['total'] ?? null,
            'passed' => (bool) ($r['passed'] ?? false),
            'trainingValid' => (bool) ($r['trainingValid'] ?? false),
            'nextRoute' => $r['nextRoute'] ?? '/terminal/login',
        ];
    }
}
