<?php

namespace App\Services\QualitySpecs;

use App\Enums\AuditAction;
use App\Enums\CalibrationProfileStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\GasComponent;
use App\Models\CalibrationComponent;
use App\Models\CalibrationProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for Calibration Profile + Component lifecycle.
 *
 * Mirrors ProductSpecificationService for the calibration domain. Read-
 * only fields on component rows (last_measured_value, last_deviation,
 * last_result) are silently stripped from input — they are set by the
 * future calibration-run interface, not by the user-edit endpoints.
 */
class CalibrationProfileService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {}

    public function createDraft(array $data): CalibrationProfile
    {
        return DB::transaction(function () use ($data) {
            // Try to resolve the device_id from analysis_devices via BMK,
            // if the table exists yet (Track A may not have landed).
            $deviceId = null;
            if (Schema::hasTable('analysis_devices')) {
                $row = DB::table('analysis_devices')
                    ->where('code', $data['device_bmk'])
                    ->first();
                $deviceId = $row?->id;
            }

            $profile = CalibrationProfile::create([
                'device_id' => $deviceId,
                'device_bmk' => $data['device_bmk'],
                'device_name' => $data['device_name'] ?? null,
                'calibration_medium' => $data['calibration_medium'],
                'certificate_ref' => $data['certificate_ref'] ?? null,
                'profile_version' => $data['profile_version'] ?? 'v1',
                'status' => CalibrationProfileStatus::DRAFT,
                'calibration_status' => CalibrationProfileStatus::CALIBRATION_STATUS_NOT_CONFIGURED,
                'lockout_behavior' => $data['lockout_behavior'],
                'medium_expiry_at' => $data['medium_expiry_at'] ?? null,
                'next_due_at' => $data['next_due_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'correlation_id' => request()?->header('X-Correlation-Id'),
            ]);

            $this->audit->record(
                $profile,
                AuditAction::CALIBRATION_PROFILE_CREATED,
                null,
                $this->audit->snapshotModel($profile),
                null,
                null
            );
            $this->events->record(
                'calibration_profile.created',
                $profile,
                "Calibration profile {$profile->device_bmk} {$profile->profile_version} created (draft)",
                ['device_bmk' => $profile->device_bmk, 'profile_version' => $profile->profile_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $profile->fresh();
        });
    }

    public function updateMetadata(CalibrationProfile $profile, array $data): CalibrationProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $old = $this->audit->snapshotModel($profile);

            $fields = [
                'device_name', 'calibration_medium', 'certificate_ref',
                'lockout_behavior', 'medium_expiry_at', 'next_due_at', 'notes',
            ];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $profile->{$f} = $data[$f];
                }
            }
            $profile->save();

            $this->audit->record(
                $profile,
                AuditAction::CALIBRATION_PROFILE_UPDATED,
                $old,
                $this->audit->snapshotModel($profile->fresh()),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'calibration_profile.updated',
                $profile,
                "Calibration profile {$profile->device_bmk} {$profile->profile_version} updated",
                ['device_bmk' => $profile->device_bmk, 'profile_version' => $profile->profile_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $profile->fresh();
        });
    }

    public function activate(CalibrationProfile $profile, ?string $reason = null): array
    {
        if ($profile->status !== CalibrationProfileStatus::DRAFT) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        $configured = $profile->components()->pluck('component')->all();
        $missing = array_values(array_diff(GasComponent::all(), $configured));
        if (count($missing) > 0) {
            return [
                'ok' => false,
                'code' => 'CALIBRATION_PROFILE_INCOMPLETE',
                'details' => ['missing' => $missing],
            ];
        }

        $fresh = DB::transaction(function () use ($profile, $reason) {
            $old = $this->audit->snapshotModel($profile);

            $profile->status = CalibrationProfileStatus::ACTIVE;
            $profile->activated_at = now();
            // calibration_status remains whatever it was unless a real
            // calibration run flips it; the activate action does NOT
            // pretend the calibration is healthy.
            $profile->save();

            $this->audit->record(
                $profile,
                AuditAction::CALIBRATION_PROFILE_ACTIVATED,
                $old,
                $this->audit->snapshotModel($profile->fresh()),
                $reason,
                null
            );
            $this->events->record(
                'calibration_profile.activated',
                $profile,
                "Calibration profile {$profile->device_bmk} {$profile->profile_version} activated",
                ['device_bmk' => $profile->device_bmk, 'profile_version' => $profile->profile_version],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $profile->fresh();
        });

        return ['ok' => true, 'profile' => $fresh];
    }

    public function retire(CalibrationProfile $profile, string $reason): array
    {
        if ($profile->status !== CalibrationProfileStatus::ACTIVE) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        $fresh = DB::transaction(function () use ($profile, $reason) {
            $old = $this->audit->snapshotModel($profile);

            $profile->status = CalibrationProfileStatus::RETIRED;
            $profile->retired_at = now();
            $profile->save();

            $this->audit->record(
                $profile,
                AuditAction::CALIBRATION_PROFILE_RETIRED,
                $old,
                $this->audit->snapshotModel($profile->fresh()),
                $reason,
                null
            );
            $this->events->record(
                'calibration_profile.retired',
                $profile,
                "Calibration profile {$profile->device_bmk} {$profile->profile_version} retired",
                ['device_bmk' => $profile->device_bmk, 'reason' => $reason],
                EventCategory::QUALITY,
                EventSeverity::WARNING
            );

            return $profile->fresh();
        });

        return ['ok' => true, 'profile' => $fresh];
    }

    public function addComponent(CalibrationProfile $profile, array $data): array
    {
        $existing = $profile->components()->where('component', $data['component'])->first();
        if ($existing) {
            return ['ok' => false, 'code' => 'CALIBRATION_COMPONENT_EXISTS'];
        }

        $row = DB::transaction(function () use ($profile, $data) {
            $row = CalibrationComponent::create([
                'profile_id' => $profile->id,
                'component' => $data['component'],
                'unit' => $data['unit'],
                'exact_value' => $data['exact_value'],
                'tolerance_abs' => $data['tolerance_abs'] ?? null,
                'tolerance_percent' => $data['tolerance_percent'] ?? null,
                'precision_decimals' => $data['precision_decimals'] ?? null,
                'rounding_rule' => $data['rounding_rule'] ?? null,
                'last_change_reason' => $data['reason'] ?? null,
            ]);

            $this->audit->record(
                $row,
                AuditAction::CALIBRATION_COMPONENT_ADDED,
                null,
                $this->audit->snapshotModel($row),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'calibration_profile.component_added',
                $row,
                "Calibration component added: {$row->component} on {$profile->device_bmk} {$profile->profile_version}",
                [
                    'profile_id' => $profile->id,
                    'device_bmk' => $profile->device_bmk,
                    'component' => $row->component,
                ],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $row;
        });

        return ['ok' => true, 'row' => $row];
    }

    public function updateComponent(CalibrationComponent $row, array $data): CalibrationComponent
    {
        return DB::transaction(function () use ($row, $data) {
            $old = $this->audit->snapshotModel($row);

            $fields = [
                'unit', 'exact_value', 'tolerance_abs', 'tolerance_percent',
                'precision_decimals', 'rounding_rule',
            ];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $row->{$f} = $data[$f];
                }
            }
            $row->last_change_reason = $data['reason'] ?? $row->last_change_reason;
            $row->save();

            $this->audit->record(
                $row,
                AuditAction::CALIBRATION_COMPONENT_UPDATED,
                $old,
                $this->audit->snapshotModel($row->fresh()),
                $data['reason'] ?? null,
                null
            );
            $this->events->record(
                'calibration_profile.component_updated',
                $row,
                "Calibration component updated: {$row->component}",
                ['profile_id' => $row->profile_id, 'component' => $row->component],
                EventCategory::QUALITY,
                EventSeverity::INFO
            );

            return $row->fresh();
        });
    }
}
