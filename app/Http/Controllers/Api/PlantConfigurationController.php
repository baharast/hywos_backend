<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Http\Requests\PlantConfiguration\ActivatePlantConfigurationRequest;
use App\Http\Requests\PlantConfiguration\StartPlantConfigurationDraftRequest;
use App\Http\Requests\PlantConfiguration\StoreGateRequest;
use App\Http\Requests\PlantConfiguration\StorePlantAreaRequest;
use App\Http\Requests\PlantConfiguration\StoreTerminalPanelRequest;
use App\Http\Requests\PlantConfiguration\SubmitChangeRequestRequest;
use App\Http\Requests\PlantConfiguration\UpdatePlantConfigurationDraftRequest;
use App\Http\Resources\GateResource;
use App\Http\Resources\PlantAreaResource;
use App\Http\Resources\PlantConfigurationChangeRequestResource;
use App\Http\Resources\PlantConfigurationResource;
use App\Http\Resources\TerminalPanelResource;
use App\Models\Gate;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\PlantConfigurationChangeRequest;
use App\Models\TerminalPanel;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\PlantConfiguration\PlantConfigurationService;
use Illuminate\Http\Request;
use RuntimeException;

class PlantConfigurationController extends ApiController
{
    public function __construct(protected PlantConfigurationService $service)
    {
    }

    /**
     * GET /api/plant-configuration
     * Return current configuration with counts and validation summary.
     */
    public function show()
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->success(null, __('plant_visits.messages.no_config'));
        }
        $config->loadCount(['plantAreas', 'gates', 'terminalsPanels', 'bayLines', 'parkings']);

        return $this->success(new PlantConfigurationResource($config), __('plant_visits.messages.config_retrieved'));
    }

    /**
     * POST /api/plant-configuration/draft
     * Start a fresh draft configuration.
     */
    public function startDraft(StartPlantConfigurationDraftRequest $request)
    {
        try {
            $config = $this->service->startDraft($request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CONFIGURATION_ALREADY_ACTIVE', 409);
        }

        return $this->created(new PlantConfigurationResource($config), __('plant_visits.messages.config_draft_started'));
    }

    /**
     * PUT /api/plant-configuration/draft
     * Update the current draft's company/site metadata.
     */
    public function updateDraft(UpdatePlantConfigurationDraftRequest $request)
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->error(__('plant_visits.messages.no_draft'), 'NO_DRAFT', 404);
        }

        try {
            $config = $this->service->updateDraft($config, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CONFIGURATION_LOCKED', 423);
        }

        return $this->success(new PlantConfigurationResource($config), __('plant_visits.messages.draft_updated'));
    }

    /**
     * POST /api/plant-configuration/validate
     * Run validation and persist the summary on the configuration.
     */
    public function validateConfiguration()
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->error(__('plant_visits.messages.no_config_to_validate'), 'NO_CONFIGURATION', 404);
        }

        $summary = $this->service->validate($config);

        return $this->success([
            'configuration' => new PlantConfigurationResource($config->fresh()),
            'validation' => $summary,
        ], __('plant_visits.messages.validation_completed'));
    }

    /**
     * GET /api/plant-configuration/review
     * Return the full summary the Review & Confirm page needs.
     */
    public function review()
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->error(__('plant_visits.messages.no_config_to_review'), 'NO_CONFIGURATION', 404);
        }

        $siteId = $config->site_id;

        $summary = $this->service->validate($config);

        return $this->success([
            'configuration' => new PlantConfigurationResource($config->fresh()),
            'companyAndSite' => [
                'companyName' => $config->company_name,
                'companyCode' => $config->company_code,
                'siteName' => $config->site_name,
                'siteCode' => $config->site_code,
                'plantType' => $config->plant_type,
                'defaultLanguage' => $config->default_language,
                'timeZone' => $config->time_zone,
            ],
            'plantAreas' => PlantAreaResource::collection(PlantArea::where('site_id', $siteId)->get()),
            'gates' => GateResource::collection(Gate::where('site_id', $siteId)->get()),
            'terminalsPanels' => TerminalPanelResource::collection(TerminalPanel::where('site_id', $siteId)->get()),
            'bayLines' => \App\Models\BayLine::where('site_id', $siteId)->get(),
            'parkingAreas' => \App\Models\Parking::where('site_id', $siteId)->get(),
            'validation' => $summary,
        ], __('plant_visits.messages.review_retrieved'));
    }

    /**
     * POST /api/plant-configuration/activate
     * Lock the configuration after explicit confirmation + re-validation.
     */
    public function activate(ActivatePlantConfigurationRequest $request)
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->error(__('plant_visits.messages.no_config_to_activate'), 'NO_CONFIGURATION', 404);
        }

        try {
            $config = $this->service->activate(
                $config,
                (bool) $request->boolean('confirmed'),
                $request->input('reason')
            );
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'already active') ? 'ALREADY_ACTIVE' : 'ACTIVATION_BLOCKED';
            return $this->error($e->getMessage(), $code, 409);
        }

        return $this->success(new PlantConfigurationResource($config), __('plant_visits.messages.config_activated'));
    }

    /**
     * GET /api/plant-configuration/objects/{type}/{id}
     */
    public function showObject(string $type, string $id)
    {
        $object = $this->service->resolveObject($type, $id);
        if (! $object) {
            return $this->error(__('plant_visits.messages.config_object_not_found'), 'OBJECT_NOT_FOUND', 404);
        }

        return $this->success($this->shapeObject($type, $object), __('plant_visits.messages.config_object_retrieved'));
    }

    /**
     * POST /api/plant-configuration/areas
     */
    public function storeArea(StorePlantAreaRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $config = $this->service->current();
        if ($config) {
            try { $this->service->assertEditable($config); }
            catch (RuntimeException $e) { return $this->error($e->getMessage(), 'CONFIGURATION_LOCKED', 423); }
        }

        $data = $request->validated();
        $area = PlantArea::create(array_merge($data, [
            'plant_configuration_id' => $config?->id,
        ]));

        $audit->recordCreated($area, AuditAction::PLANT_CONFIG_OBJECT_CREATED);
        $events->record('plant_config.area_created', $area, "Plant area {$area->code} created", null, EventCategory::SYSTEM);

        return $this->created(new PlantAreaResource($area), __('plant_visits.messages.area_created'));
    }

    /**
     * POST /api/plant-configuration/gates
     */
    public function storeGate(StoreGateRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $config = $this->service->current();
        if ($config) {
            try { $this->service->assertEditable($config); }
            catch (RuntimeException $e) { return $this->error($e->getMessage(), 'CONFIGURATION_LOCKED', 423); }
        }

        $data = $request->validated();
        $gate = Gate::create(array_merge($data, [
            'plant_configuration_id' => $config?->id,
        ]));

        $audit->recordCreated($gate, AuditAction::PLANT_CONFIG_OBJECT_CREATED);
        $events->record('plant_config.gate_created', $gate, "Gate {$gate->code} ({$gate->gate_type}) created", null, EventCategory::SYSTEM);

        return $this->created(new GateResource($gate), __('plant_visits.messages.gate_created'));
    }

    /**
     * POST /api/plant-configuration/terminals
     */
    public function storeTerminal(StoreTerminalPanelRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $config = $this->service->current();
        if ($config) {
            try { $this->service->assertEditable($config); }
            catch (RuntimeException $e) { return $this->error($e->getMessage(), 'CONFIGURATION_LOCKED', 423); }
        }

        $data = $request->validated();
        $terminal = TerminalPanel::create(array_merge($data, [
            'plant_configuration_id' => $config?->id,
        ]));

        $audit->recordCreated($terminal, AuditAction::PLANT_CONFIG_OBJECT_CREATED);
        $events->record('plant_config.terminal_created', $terminal, "Terminal {$terminal->code} ({$terminal->terminal_type}) created", null, EventCategory::SYSTEM);

        return $this->created(new TerminalPanelResource($terminal), __('plant_visits.messages.terminal_created'));
    }

    /**
     * POST /api/plant-configuration/change-requests
     */
    public function submitChangeRequest(SubmitChangeRequestRequest $request)
    {
        $config = $this->service->current();
        if (! $config) {
            return $this->error(__('plant_visits.messages.no_config_to_change'), 'NO_CONFIGURATION', 404);
        }

        try {
            $cr = $this->service->submitChangeRequest(
                $config,
                $request->input('affected_object_type'),
                $request->input('affected_object_id'),
                $request->input('change_type'),
                $request->input('proposed_values', []),
                $request->input('reason'),
                $request->input('reason_code')
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CHANGE_REQUEST_REJECTED', 409);
        }

        return $this->created(new PlantConfigurationChangeRequestResource($cr), __('plant_visits.messages.change_request_submitted'));
    }

    /**
     * GET /api/plant-configuration/change-requests/{id}
     */
    public function showChangeRequest(string $id)
    {
        $cr = PlantConfigurationChangeRequest::find($id);
        if (! $cr) {
            return $this->error(__('plant_visits.messages.change_request_not_found'), 'CHANGE_REQUEST_NOT_FOUND', 404);
        }
        return $this->success(new PlantConfigurationChangeRequestResource($cr), __('plant_visits.messages.change_request_retrieved'));
    }

    /**
     * POST /api/plant-configuration/change-requests/{id}/approve
     */
    public function approveChangeRequest(Request $request, string $id)
    {
        $cr = PlantConfigurationChangeRequest::find($id);
        if (! $cr) {
            return $this->error(__('plant_visits.messages.change_request_not_found'), 'CHANGE_REQUEST_NOT_FOUND', 404);
        }

        try {
            $cr = $this->service->approveChangeRequest($cr, $request->input('note'));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CHANGE_REQUEST_INVALID_STATE', 409);
        }

        return $this->success(new PlantConfigurationChangeRequestResource($cr), __('plant_visits.messages.change_request_approved'));
    }

    /**
     * POST /api/plant-configuration/change-requests/{id}/apply
     */
    public function applyChangeRequest(string $id)
    {
        $cr = PlantConfigurationChangeRequest::find($id);
        if (! $cr) {
            return $this->error(__('plant_visits.messages.change_request_not_found'), 'CHANGE_REQUEST_NOT_FOUND', 404);
        }

        try {
            $cr = $this->service->applyChangeRequest($cr);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CHANGE_REQUEST_INVALID_STATE', 409);
        }

        return $this->success(new PlantConfigurationChangeRequestResource($cr), __('plant_visits.messages.change_request_applied'));
    }

    /**
     * POST /api/plant-configuration/change-requests/{id}/reject
     */
    public function rejectChangeRequest(Request $request, string $id)
    {
        $cr = PlantConfigurationChangeRequest::find($id);
        if (! $cr) {
            return $this->error(__('plant_visits.messages.change_request_not_found'), 'CHANGE_REQUEST_NOT_FOUND', 404);
        }

        $note = (string) $request->input('note', '');
        if ($note === '') {
            return $this->error(__('plant_visits.messages.rejection_note_required'), 'NOTE_REQUIRED', 422);
        }

        try {
            $cr = $this->service->rejectChangeRequest($cr, $note);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'CHANGE_REQUEST_INVALID_STATE', 409);
        }

        return $this->success(new PlantConfigurationChangeRequestResource($cr), __('plant_visits.messages.change_request_rejected'));
    }

    protected function shapeObject(string $type, $object)
    {
        return match ($type) {
            'plant_area' => new PlantAreaResource($object),
            'gate' => new GateResource($object),
            'terminal_panel', 'terminal' => new TerminalPanelResource($object),
            default => $object,
        };
    }
}
