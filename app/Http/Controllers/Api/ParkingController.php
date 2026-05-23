<?php

namespace App\Http\Controllers\Api;

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

        if ($request->filled('status')) {
            $query->where('status_code', $request->query('status'));
        }

        if ($request->filled('space_type')) {
            $query->where('space_type', $request->query('space_type'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->query('area_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $totalCapacity = (int) Parking::query()->sum('capacity');
        $totalOccupied = (int) Parking::query()->sum('occupied_count');

        $summary = [
            'total' => Parking::query()->count(),
            'totalCapacity' => $totalCapacity,
            'totalOccupied' => $totalOccupied,
            'totalAvailable' => max(0, $totalCapacity - $totalOccupied),
            'active' => Parking::query()->where('is_active', true)->count(),
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
        $this->authorize('delete', Parking::class);

        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        // Soft disable for safety
        $parking->update(['is_active' => false]);

        return $this->success(null, 'Parking deactivated');
    }
}
