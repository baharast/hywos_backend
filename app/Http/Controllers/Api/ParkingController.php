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
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->query('area_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return \App\Services\ApiResponse::paginated($paginator, 'Parkings retrieved');
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
