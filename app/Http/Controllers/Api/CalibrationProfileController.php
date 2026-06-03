<?php

namespace App\Http\Controllers\Api;

use App\Enums\CalibrationProfileStatus;
use App\Http\Requests\CalibrationProfile\AddCalibrationComponentRequest;
use App\Http\Requests\CalibrationProfile\CreateCalibrationProfileRequest;
use App\Http\Requests\CalibrationProfile\RetireCalibrationProfileRequest;
use App\Http\Requests\CalibrationProfile\UpdateCalibrationComponentRequest;
use App\Http\Requests\CalibrationProfile\UpdateCalibrationProfileRequest;
use App\Http\Resources\CalibrationComponentResource;
use App\Http\Resources\CalibrationProfileDetailResource;
use App\Http\Resources\CalibrationProfileListResource;
use App\Models\CalibrationComponent;
use App\Models\CalibrationProfile;
use App\Services\ApiResponse;
use App\Services\QualitySpecs\CalibrationProfileService;
use Illuminate\Http\Request;

class CalibrationProfileController extends ApiController
{
    public function __construct(protected CalibrationProfileService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = CalibrationProfile::query()->withCount('components')->with('components');

        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($cs = $request->query('calibration_status')) {
            $query->where('calibration_status', $cs);
        }
        if ($bmk = $request->query('device_bmk')) {
            $query->where('device_bmk', $bmk);
        }
        if ($q = $request->query('q')) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('device_bmk', 'like', $like)
                    ->orWhere('device_name', 'like', $like)
                    ->orWhere('calibration_medium', 'like', $like)
                    ->orWhere('certificate_ref', 'like', $like);
            });
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);
        $rows = CalibrationProfileListResource::collection($paginator->items());

        $summary = [
            'total' => CalibrationProfile::query()->count(),
            'active' => CalibrationProfile::query()->where('status', CalibrationProfileStatus::ACTIVE)->count(),
            'draft' => CalibrationProfile::query()->where('status', CalibrationProfileStatus::DRAFT)->count(),
            'overdue' => CalibrationProfile::query()->where('calibration_status', CalibrationProfileStatus::CALIBRATION_STATUS_OVERDUE)->count(),
            'failed' => CalibrationProfile::query()->where('calibration_status', CalibrationProfileStatus::CALIBRATION_STATUS_FAILED)->count(),
        ];

        return ApiResponse::list(
            $rows,
            $paginator,
            $summary,
            CalibrationProfile::query()->max('updated_at'),
            __('masterdata.messages.profiles_retrieved')
        );
    }

    public function show(string $id)
    {
        $profile = CalibrationProfile::with('components')->find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        return $this->success(new CalibrationProfileDetailResource($profile), __('masterdata.messages.profile_retrieved'));
    }

    public function store(CreateCalibrationProfileRequest $request)
    {
        $profile = $this->service->createDraft($request->validated());
        return $this->created(new CalibrationProfileDetailResource($profile->load('components')), __('masterdata.messages.profile_created'));
    }

    public function update(UpdateCalibrationProfileRequest $request, string $id)
    {
        $profile = CalibrationProfile::find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        $fresh = $this->service->updateMetadata($profile, $request->validated());
        return $this->success(new CalibrationProfileDetailResource($fresh->load('components')), __('masterdata.messages.profile_updated'));
    }

    public function activate(Request $request, string $id)
    {
        $profile = CalibrationProfile::find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        $result = $this->service->activate($profile, $request->input('reason'));
        if (! ($result['ok'] ?? false)) {
            return $this->error(
                $result['code'] === 'CALIBRATION_PROFILE_INCOMPLETE'
                    ? __('masterdata.messages.profile_incomplete')
                    : __('masterdata.messages.profile_cannot_activate'),
                $result['code'] ?? 'INVALID_STATE_TRANSITION',
                409,
                $result['details'] ?? null
            );
        }
        return $this->success(new CalibrationProfileDetailResource($result['profile']->load('components')), __('masterdata.messages.profile_activated'));
    }

    public function retire(RetireCalibrationProfileRequest $request, string $id)
    {
        $profile = CalibrationProfile::find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        $result = $this->service->retire($profile, $request->validated()['reason']);
        if (! ($result['ok'] ?? false)) {
            return $this->error(__('masterdata.messages.profile_cannot_retire'), $result['code'] ?? 'INVALID_STATE_TRANSITION', 409);
        }
        return $this->success(new CalibrationProfileDetailResource($result['profile']->load('components')), __('masterdata.messages.profile_retired'));
    }

    public function addComponent(AddCalibrationComponentRequest $request, string $id)
    {
        $profile = CalibrationProfile::find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        if (! $profile->isEditable()) {
            return $this->error(__('masterdata.messages.profile_not_editable'), 'CALIBRATION_PROFILE_NOT_EDITABLE', 409);
        }

        $result = $this->service->addComponent($profile, $request->validated());
        if (! ($result['ok'] ?? false)) {
            return $this->error(
                __('masterdata.messages.cal_component_exists'),
                $result['code'] ?? 'CALIBRATION_COMPONENT_EXISTS',
                409
            );
        }
        return $this->created(new CalibrationComponentResource($result['row']), __('masterdata.messages.cal_component_added'));
    }

    public function updateComponent(UpdateCalibrationComponentRequest $request, string $id, string $rowId)
    {
        $profile = CalibrationProfile::find($id);
        if (! $profile) {
            return $this->error(__('masterdata.messages.profile_not_found'), 'CALIBRATION_PROFILE_NOT_FOUND', 404);
        }
        if (! $profile->isEditable()) {
            return $this->error(__('masterdata.messages.profile_not_editable'), 'CALIBRATION_PROFILE_NOT_EDITABLE', 409);
        }

        $row = CalibrationComponent::where('id', $rowId)->where('profile_id', $profile->id)->first();
        if (! $row) {
            return $this->error(__('masterdata.messages.cal_component_not_found'), 'CALIBRATION_COMPONENT_NOT_FOUND', 404);
        }

        $fresh = $this->service->updateComponent($row, $request->validated());
        return $this->success(new CalibrationComponentResource($fresh), __('masterdata.messages.cal_component_updated'));
    }
}
