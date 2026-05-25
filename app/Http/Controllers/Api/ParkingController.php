<?php

namespace App\Http\Controllers\Api;

use App\Enums\ParkingSlotStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;
use App\Http\Resources\ParkingResource;
use App\Models\Parking;
use Illuminate\Http\Request;

class ParkingController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Parking::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        if ($request->filled('slot_status')) {
            $query->where('slot_status', $request->query('slot_status'));
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }
        if ($request->filled('plant_area_id')) {
            $query->where('plant_area_id', $request->query('plant_area_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $base = Parking::query();
        $summary = [
            'total' => (clone $base)->count(),
            'free' => (clone $base)->where('slot_status', ParkingSlotStatus::FREE)->count(),
            'occupied' => (clone $base)->where('slot_status', ParkingSlotStatus::OCCUPIED)->count(),
            'blocked' => (clone $base)->whereIn('slot_status', [
                ParkingSlotStatus::BLOCKED,
                ParkingSlotStatus::OUT_OF_SERVICE,
            ])->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
        ];

        $paginator = $query->orderBy('code')->paginate($perPage);
        $items = ParkingResource::collection($paginator->items());
        $lastUpdated = Parking::query()->max('updated_at');

        return \App\Services\ApiResponse::list($items, $paginator, $summary, $lastUpdated, 'Parkings retrieved');
    }

    public function store(StoreParkingRequest $request)
    {
        $data = $request->validated();
        $parking = Parking::create($data);

        return $this->created(new ParkingResource($parking), 'Parking created');
    }

    public function show($id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        return $this->success(new ParkingResource($parking), 'Parking retrieved');
    }

    public function update(UpdateParkingRequest $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update($request->validated());

        return $this->success(new ParkingResource($parking), 'Parking updated');
    }

    public function activate(Request $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => true]);

        return $this->success(new ParkingResource($parking), 'Parking activated');
    }

    public function deactivate(Request $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => false]);

        return $this->success(new ParkingResource($parking), 'Parking deactivated');
    }

    public function destroy(Request $request, $id)
    {
        // V2.1 — slots are part of the fixed site layout, not user-created
        // master data. DELETE acts as a soft deactivate so the row + history
        // remain. Auth is intentionally disabled in this MVP phase.
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => false]);

        return $this->success(null, 'Parking deactivated');
    }
}
