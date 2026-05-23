<?php

namespace App\Services\PlantConfiguration;

use App\Enums\AuditAction;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\GateType;
use App\Enums\PlantConfigurationStatus;
use App\Models\BayLine;
use App\Models\Gate;
use App\Models\Parking;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\PlantConfigurationChangeRequest;
use App\Models\Site;
use App\Models\TerminalPanel;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlantConfigurationService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {
    }

    /**
     * Return the configuration that the dashboard should treat as "current".
     * Priority: active_locked > pending_confirmation > draft/incomplete > most recent.
     */
    public function current(): ?PlantConfiguration
    {
        return PlantConfiguration::query()
            ->orderByRaw("FIELD(status, '" . PlantConfigurationStatus::ACTIVE_LOCKED . "', '"
                . PlantConfigurationStatus::CHANGE_REQUESTED . "', '"
                . PlantConfigurationStatus::PENDING_CONFIRMATION . "', '"
                . PlantConfigurationStatus::DRAFT . "', '"
                . PlantConfigurationStatus::INCOMPLETE . "', '"
                . PlantConfigurationStatus::INACTIVE . "')")
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Start a new draft configuration. Fails if an active_locked one already exists.
     */
    public function startDraft(array $data): PlantConfiguration
    {
        $active = PlantConfiguration::query()
            ->where('status', PlantConfigurationStatus::ACTIVE_LOCKED)
            ->first();
        if ($active) {
            throw new RuntimeException('An active configuration already exists. Use change requests instead.');
        }

        return DB::transaction(function () use ($data) {
            $site = $this->resolveOrCreateSite($data);

            $config = PlantConfiguration::create([
                'site_id' => $site->id,
                'company_id' => $data['company_id'] ?? null,
                'status' => PlantConfigurationStatus::DRAFT,
                'company_name' => $data['company_name'] ?? null,
                'company_code' => $data['company_code'] ?? null,
                'site_name' => $data['site_name'] ?? $site->name,
                'site_code' => $data['site_code'] ?? $site->code,
                'plant_type' => $data['plant_type'] ?? $site->plant_type,
                'default_language' => $data['default_language'] ?? ($site->default_language ?: 'de'),
                'time_zone' => $data['time_zone'] ?? ($site->time_zone ?: 'Europe/Berlin'),
            ]);

            $this->audit->recordCreated($config, AuditAction::PLANT_CONFIG_DRAFT_STARTED);
            $this->events->record(
                'plant_config.draft_started',
                $config,
                "Plant configuration draft started for site {$site->code}",
                ['site_id' => $site->id],
                EventCategory::SYSTEM,
                EventSeverity::INFO
            );

            return $config;
        });
    }

    /**
     * Update the draft's company/site descriptive metadata. Structural objects
     * are managed through their own create/update endpoints.
     */
    public function updateDraft(PlantConfiguration $config, array $data): PlantConfiguration
    {
        $this->assertEditable($config);

        return DB::transaction(function () use ($config, $data) {
            $old = $this->audit->snapshotModel($config);

            $config->update(array_filter([
                'company_name' => $data['company_name'] ?? null,
                'company_code' => $data['company_code'] ?? null,
                'site_name' => $data['site_name'] ?? null,
                'site_code' => $data['site_code'] ?? null,
                'plant_type' => $data['plant_type'] ?? null,
                'default_language' => $data['default_language'] ?? null,
                'time_zone' => $data['time_zone'] ?? null,
            ], fn ($v) => ! is_null($v)));

            $this->audit->recordUpdated($config, $old, AuditAction::PLANT_CONFIG_DRAFT_SAVED);

            return $config->fresh();
        });
    }

    /**
     * Run all activation-readiness checks. Result is also persisted on the configuration
     * so the review page can read it without re-running checks.
     */
    public function validate(PlantConfiguration $config): array
    {
        $errors = [];
        $warnings = [];
        $completed = [];
        $incomplete = [];

        // Step 1 — Company & Site
        if ($config->company_name && $config->company_code
            && $config->site_name && $config->site_code
            && $config->plant_type && $config->default_language && $config->time_zone) {
            $completed[] = 'company_site';
        } else {
            $incomplete[] = 'company_site';
            $errors[] = 'Company & site fields are incomplete.';
        }

        // Step 2 — Plant areas
        $areas = PlantArea::query()->where('site_id', $config->site_id)->get();
        if ($areas->isEmpty()) {
            $incomplete[] = 'plant_areas';
            $errors[] = 'At least one plant area is required.';
        } else {
            $completed[] = 'plant_areas';
            $this->collectDuplicateCodes($errors, $areas, 'Plant area');
        }

        // Step 3 — Gates (≥1 entry, ≥1 exit)
        $gates = Gate::query()->where('site_id', $config->site_id)->get();
        $entryCount = $gates->where('gate_type', GateType::ENTRY)->count()
            + $gates->where('gate_type', GateType::COMBINED)->count();
        $exitCount = $gates->where('gate_type', GateType::EXIT)->count()
            + $gates->where('gate_type', GateType::COMBINED)->count();
        if ($entryCount < 1) {
            $incomplete[] = 'gates';
            $errors[] = 'At least one entry gate is required.';
        } elseif ($exitCount < 1) {
            $incomplete[] = 'gates';
            $errors[] = 'At least one exit gate is required.';
        } else {
            $completed[] = 'gates';
            $this->collectDuplicateCodes($errors, $gates, 'Gate');
            $this->collectUnlinkedArea($warnings, $gates, 'Gate');
        }

        // Step 4 — Terminals & panels (recommended, not blocking)
        $terminals = TerminalPanel::query()->where('site_id', $config->site_id)->get();
        if ($terminals->isEmpty()) {
            $warnings[] = 'No terminals or panels configured. Hardware integration may be limited.';
        } else {
            $completed[] = 'terminals_panels';
            $this->collectDuplicateCodes($errors, $terminals, 'Terminal/panel');
        }

        // Step 5 — Bay lines (≥1 required)
        $bayLines = BayLine::query()->where('site_id', $config->site_id)->get();
        if ($bayLines->isEmpty()) {
            $incomplete[] = 'bay_lines';
            $errors[] = 'At least one filling station / bay line is required.';
        } else {
            $completed[] = 'bay_lines';
            $this->collectDuplicateCodes($errors, $bayLines, 'Bay line');
            $this->collectUnlinkedArea($warnings, $bayLines, 'Bay line', 'plant_area_id');
        }

        // Step 6 — Parking areas (required for park/pickup variants — treated as blocking by default)
        $parkings = Parking::query()->where('site_id', $config->site_id)->get();
        if ($parkings->isEmpty()) {
            $incomplete[] = 'parking_areas';
            $errors[] = 'At least one parking area is required for park/pickup variants.';
        } else {
            $completed[] = 'parking_areas';
            $this->collectDuplicateCodes($errors, $parkings, 'Parking area');
            $this->collectUnlinkedArea($warnings, $parkings, 'Parking area', 'area_id');
        }

        $summary = [
            'isValidForActivation' => empty($errors),
            'blockingErrors' => $errors,
            'warnings' => $warnings,
            'completedSteps' => $completed,
            'incompleteSteps' => $incomplete,
            'counts' => [
                'plantAreas' => $areas->count(),
                'gates' => $gates->count(),
                'terminalsPanels' => $terminals->count(),
                'bayLines' => $bayLines->count(),
                'parkingAreas' => $parkings->count(),
            ],
            'validatedAt' => now()->toIso8601String(),
        ];

        $config->update([
            'validation_summary' => $summary,
            'status' => empty($errors)
                ? PlantConfigurationStatus::PENDING_CONFIRMATION
                : ($config->status === PlantConfigurationStatus::ACTIVE_LOCKED
                    ? PlantConfigurationStatus::ACTIVE_LOCKED
                    : PlantConfigurationStatus::INCOMPLETE),
        ]);

        $this->events->record(
            'plant_config.validated',
            $config,
            empty($errors) ? 'Plant configuration validated and ready for activation' : 'Plant configuration validation failed',
            $summary,
            EventCategory::SYSTEM,
            empty($errors) ? EventSeverity::INFO : EventSeverity::WARNING
        );

        return $summary;
    }

    /**
     * Activate the configuration. Re-validates first. Requires explicit confirmation payload.
     */
    public function activate(PlantConfiguration $config, bool $confirmed, ?string $reason = null): PlantConfiguration
    {
        if (! $confirmed) {
            throw new RuntimeException('Activation requires explicit confirmation.');
        }
        if ($config->isLocked()) {
            throw new RuntimeException('Configuration is already active and locked.');
        }

        $summary = $this->validate($config->fresh());
        if (! ($summary['isValidForActivation'] ?? false)) {
            throw new RuntimeException('Configuration is not valid for activation.');
        }

        return DB::transaction(function () use ($config, $reason) {
            $old = $this->audit->snapshotModel($config);

            $config->update([
                'status' => PlantConfigurationStatus::ACTIVE_LOCKED,
                'activated_at' => now(),
                'version' => ($config->version ?? 1),
            ]);

            // Lock all structural objects to this configuration version.
            $this->linkObjectsToConfiguration($config);

            $this->audit->recordUpdated(
                $config,
                $old,
                AuditAction::PLANT_CONFIG_ACTIVATED,
                $reason ?? 'Initial plant configuration activation'
            );

            $this->events->record(
                'plant_config.activated',
                $config,
                "Plant configuration v{$config->version} activated for site {$config->site_code}",
                ['version' => $config->version, 'reason' => $reason],
                EventCategory::SYSTEM,
                EventSeverity::INFO
            );

            return $config->fresh();
        });
    }

    /**
     * Submit a controlled change request against an active configuration.
     */
    public function submitChangeRequest(
        PlantConfiguration $config,
        string $affectedObjectType,
        string $affectedObjectId,
        string $changeType,
        array $proposedValues,
        string $reason,
        ?string $reasonCode = null
    ): PlantConfigurationChangeRequest {
        if (! $config->isLocked()) {
            throw new RuntimeException('Change requests are only allowed against an active/locked configuration.');
        }

        $object = $this->resolveObject($affectedObjectType, $affectedObjectId);
        if (! $object) {
            throw new RuntimeException("Affected object {$affectedObjectType}:{$affectedObjectId} not found.");
        }

        return DB::transaction(function () use ($config, $affectedObjectType, $affectedObjectId, $changeType, $proposedValues, $reason, $reasonCode, $object) {
            $currentValues = $this->audit->snapshotModel($object);
            $label = $object->name ?? ($object->code ?? $object->getKey());

            $request = PlantConfigurationChangeRequest::create([
                'plant_configuration_id' => $config->id,
                'affected_object_type' => $affectedObjectType,
                'affected_object_id' => $affectedObjectId,
                'affected_object_label' => $label,
                'change_type' => $changeType,
                'current_values' => $currentValues,
                'proposed_values' => $proposedValues,
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'status' => ChangeRequestStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            $config->update(['status' => PlantConfigurationStatus::CHANGE_REQUESTED]);

            $this->audit->recordCreated($request, AuditAction::PLANT_CONFIG_CHANGE_REQUESTED, $reason);
            $this->events->record(
                'plant_config.change_requested',
                $request,
                "Change request submitted for {$affectedObjectType} {$label}",
                ['change_type' => $changeType, 'reason' => $reason],
                EventCategory::SYSTEM,
                EventSeverity::WARNING
            );

            return $request;
        });
    }

    /**
     * Approve a change request without applying it (a second step is required to apply).
     */
    public function approveChangeRequest(PlantConfigurationChangeRequest $request, ?string $note = null): PlantConfigurationChangeRequest
    {
        if ($request->status !== ChangeRequestStatus::SUBMITTED) {
            throw new RuntimeException('Only submitted change requests can be approved.');
        }

        return DB::transaction(function () use ($request, $note) {
            $old = $this->audit->snapshotModel($request);

            $request->update([
                'status' => ChangeRequestStatus::APPROVED,
                'approved_at' => now(),
            ]);

            $this->audit->recordUpdated($request, $old, AuditAction::PLANT_CONFIG_CHANGE_APPROVED, $note);
            $this->events->record(
                'plant_config.change_approved',
                $request,
                "Change request {$request->id} approved",
                ['note' => $note],
                EventCategory::SYSTEM
            );

            return $request->fresh();
        });
    }

    /**
     * Apply an approved change request: write proposed values to the target object.
     */
    public function applyChangeRequest(PlantConfigurationChangeRequest $request): PlantConfigurationChangeRequest
    {
        if ($request->status !== ChangeRequestStatus::APPROVED) {
            throw new RuntimeException('Only approved change requests can be applied.');
        }

        $object = $this->resolveObject($request->affected_object_type, $request->affected_object_id);
        if (! $object) {
            throw new RuntimeException('Affected object no longer exists.');
        }

        return DB::transaction(function () use ($request, $object) {
            $oldObject = $this->audit->snapshotModel($object);
            $object->update($request->proposed_values ?? []);

            $oldRequest = $this->audit->snapshotModel($request);
            $request->update([
                'status' => ChangeRequestStatus::APPLIED,
                'applied_at' => now(),
            ]);

            $this->audit->recordUpdated(
                $object,
                $oldObject,
                AuditAction::PLANT_CONFIG_CHANGE_APPLIED,
                $request->reason
            );
            $this->audit->recordUpdated($request, $oldRequest, AuditAction::PLANT_CONFIG_CHANGE_APPLIED);

            $this->events->record(
                'plant_config.change_applied',
                $object,
                "Change request {$request->id} applied to {$request->affected_object_type}",
                ['change_request_id' => $request->id],
                EventCategory::SYSTEM
            );

            // Return the lock state if no other change request is open.
            $config = $request->plantConfiguration;
            if ($config) {
                $openCount = PlantConfigurationChangeRequest::query()
                    ->where('plant_configuration_id', $config->id)
                    ->whereIn('status', [ChangeRequestStatus::SUBMITTED, ChangeRequestStatus::APPROVED])
                    ->count();
                if ($openCount === 0) {
                    $config->update(['status' => PlantConfigurationStatus::ACTIVE_LOCKED]);
                }
            }

            return $request->fresh();
        });
    }

    /**
     * Reject a change request with a note.
     */
    public function rejectChangeRequest(PlantConfigurationChangeRequest $request, string $note): PlantConfigurationChangeRequest
    {
        if (! in_array($request->status, [ChangeRequestStatus::SUBMITTED, ChangeRequestStatus::APPROVED], true)) {
            throw new RuntimeException('Only submitted or approved change requests can be rejected.');
        }

        return DB::transaction(function () use ($request, $note) {
            $old = $this->audit->snapshotModel($request);

            $request->update([
                'status' => ChangeRequestStatus::REJECTED,
                'rejected_at' => now(),
                'rejection_note' => $note,
            ]);

            $this->audit->recordUpdated($request, $old, AuditAction::PLANT_CONFIG_CHANGE_REJECTED, $note);
            $this->events->record(
                'plant_config.change_rejected',
                $request,
                "Change request {$request->id} rejected",
                ['note' => $note],
                EventCategory::SYSTEM,
                EventSeverity::WARNING
            );

            return $request->fresh();
        });
    }

    /**
     * Resolve a structural object by its type slug and id.
     * Returns null if the type is unknown or the object does not exist.
     */
    public function resolveObject(string $type, string $id): ?Model
    {
        $class = match ($type) {
            'plant_area' => PlantArea::class,
            'gate' => Gate::class,
            'terminal_panel', 'terminal' => TerminalPanel::class,
            'bay_line', 'bayline' => BayLine::class,
            'parking_area', 'parking' => Parking::class,
            'plant_configuration' => PlantConfiguration::class,
            default => null,
        };

        return $class ? $class::find($id) : null;
    }

    public function assertEditable(PlantConfiguration $config): void
    {
        if ($config->isLocked()) {
            throw new RuntimeException('Configuration is locked. Use change requests to modify structural objects.');
        }
    }

    protected function resolveOrCreateSite(array $data): Site
    {
        if (! empty($data['site_id'])) {
            $site = Site::find($data['site_id']);
            if ($site) {
                return $site;
            }
        }
        if (! empty($data['site_code'])) {
            $site = Site::where('code', $data['site_code'])->first();
            if ($site) {
                return $site;
            }
        }

        return Site::create([
            'code' => $data['site_code'] ?? 'SITE-' . substr((string) \Illuminate\Support\Str::uuid(), 0, 8),
            'name' => $data['site_name'] ?? 'Default Site',
            'company_id' => $data['company_id'] ?? null,
            'plant_type' => $data['plant_type'] ?? null,
            'default_language' => $data['default_language'] ?? 'de',
            'time_zone' => $data['time_zone'] ?? 'Europe/Berlin',
        ]);
    }

    protected function linkObjectsToConfiguration(PlantConfiguration $config): void
    {
        $siteId = $config->site_id;

        PlantArea::query()->where('site_id', $siteId)->whereNull('plant_configuration_id')
            ->update(['plant_configuration_id' => $config->id, 'status' => 'active']);

        Gate::query()->where('site_id', $siteId)->whereNull('plant_configuration_id')
            ->update(['plant_configuration_id' => $config->id, 'status' => 'active']);

        TerminalPanel::query()->where('site_id', $siteId)->whereNull('plant_configuration_id')
            ->update(['plant_configuration_id' => $config->id, 'status' => 'active']);

        BayLine::query()->where('site_id', $siteId)->whereNull('plant_configuration_id')
            ->update(['plant_configuration_id' => $config->id]);

        Parking::query()->where('site_id', $siteId)->whereNull('plant_configuration_id')
            ->update(['plant_configuration_id' => $config->id]);
    }

    protected function collectDuplicateCodes(array &$errors, $collection, string $label): void
    {
        $codes = $collection->groupBy('code');
        foreach ($codes as $code => $items) {
            if ($items->count() > 1) {
                $errors[] = "Duplicate {$label} code: {$code}.";
            }
        }
    }

    protected function collectUnlinkedArea(array &$warnings, $collection, string $label, string $field = 'plant_area_id'): void
    {
        foreach ($collection as $item) {
            if (empty($item->{$field})) {
                $warnings[] = "{$label} {$item->code} is not linked to a plant area.";
            }
        }
    }

    public function changeTypeIsAllowed(string $changeType): bool
    {
        return in_array($changeType, ChangeRequestType::all(), true);
    }
}
