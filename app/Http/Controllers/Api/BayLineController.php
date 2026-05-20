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
        $paginator = BayLine::query()->paginate($perPage);

        return \App\Services\ApiResponse::paginated($paginator, 'BayLines retrieved');
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
        $this->authorize('delete', BayLine::class);

        $bayline = BayLine::find($id);
        if (! $bayline) {
            return $this->error('BayLine not found', 'BAYLINE_NOT_FOUND', 404);
        }

        // soft disable
        $bayline->update(['is_active' => false]);

        return $this->success(null, 'BayLine deactivated');
    }
}
