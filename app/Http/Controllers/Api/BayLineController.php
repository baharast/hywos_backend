<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreBayLineRequest;
use App\Http\Requests\UpdateBayLineRequest;
use App\Http\Resources\BayLineResource;
use App\Models\BayLine;
use Illuminate\Http\Request;

class BayLineController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = BayLine::query();

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

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        if ($request->filled('plant_area_id')) {
            $query->where('plant_area_id', $request->query('plant_area_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $summary = [
            'total' => BayLine::query()->count(),
            'free' => BayLine::query()->where('status_code', 'free')->count(),
            'loading' => BayLine::query()->where('status_code', 'loading')->count(),
            'fault' => BayLine::query()->where('status_code', 'fault')->count(),
            'maintenance' => BayLine::query()->where('status_code', 'maintenance')->count(),
        ];

        $paginator = $query->orderBy('code')->paginate($perPage);
        $items = BayLineResource::collection($paginator->items());
        $lastUpdated = BayLine::query()->max('updated_at');

        return \App\Services\ApiResponse::list($items, $paginator, $summary, $lastUpdated, 'BayLines retrieved');
    }

    public function store(StoreBayLineRequest $request)
    {
        $data = $request->validated();
        $bayline = BayLine::create($data);

        return $this->created(new BayLineResource($bayline), 'BayLine created');
    }

    public function show($id)
    {
        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        return $this->success(new BayLineResource($bayline), 'BayLine retrieved');
    }

    public function update(UpdateBayLineRequest $request, $id)
    {
        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        $bayline->update($request->validated());

        return $this->success(new BayLineResource($bayline), 'BayLine updated');
    }

    public function activate(Request $request, $id)
    {
        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        $bayline->update(['is_active' => true]);

        return $this->success(new BayLineResource($bayline), 'BayLine activated');
    }

    public function deactivate(Request $request, $id)
    {
        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        $bayline->update(['is_active' => false]);

        return $this->success(new BayLineResource($bayline), 'BayLine deactivated');
    }

    public function destroy(Request $request, $id)
    {
        // Authorisation is owned by the route middleware
        // (`permission:plant_configuration.manage` on DELETE
        // /api/baylines/{id}). The previous Policy lookup
        // (`$this->authorize('delete', BayLine::class)`) had no
        // matching policy class so it fell through to Laravel's
        // default deny and 403'd every caller — including admin.
        // Single source of truth = the route gate.

        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        // soft disable
        $bayline->update(['is_active' => false]);

        return $this->success(null, 'BayLine deactivated');
    }
}
