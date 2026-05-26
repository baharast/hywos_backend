<?php

namespace App\Http\Controllers\Api;

use App\Enums\GasComponent;
use App\Enums\ProductSpecStatus;
use App\Http\Requests\ProductSpecification\AddGasLimitRequest;
use App\Http\Requests\ProductSpecification\CreateProductSpecificationRequest;
use App\Http\Requests\ProductSpecification\RetireProductSpecificationRequest;
use App\Http\Requests\ProductSpecification\UpdateGasLimitRequest;
use App\Http\Requests\ProductSpecification\UpdateProductSpecificationRequest;
use App\Http\Resources\ProductGasLimitResource;
use App\Http\Resources\ProductSpecificationDetailResource;
use App\Http\Resources\ProductSpecificationListResource;
use App\Models\ProductGasLimit;
use App\Models\ProductSpecification;
use App\Services\ApiResponse;
use App\Services\QualitySpecs\ProductSpecificationService;
use Illuminate\Http\Request;

/**
 * Products & Quality Specifications (V2.1) — Product Specifications tab.
 *
 * Thin HTTP shell. Every state change passes through
 * ProductSpecificationService; this controller only resolves models,
 * shapes responses and translates service result codes into HTTP errors.
 */
class ProductSpecificationController extends ApiController
{
    public function __construct(protected ProductSpecificationService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = ProductSpecification::query()->withCount('gasLimits')->with('gasLimits');

        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($code = $request->query('product_code')) {
            $query->where('product_code', $code);
        }
        if ($q = $request->query('q')) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('product_code', 'like', $like)
                    ->orWhere('display_name', 'like', $like)
                    ->orWhere('quality_class', 'like', $like);
            });
        }

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, ['updated_at', 'created_at', 'product_code', 'spec_version', 'status'], true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = ProductSpecificationListResource::collection($paginator->items());

        $summary = [
            'total' => ProductSpecification::query()->count(),
            'active' => ProductSpecification::query()->where('status', ProductSpecStatus::ACTIVE)->count(),
            'draft' => ProductSpecification::query()->where('status', ProductSpecStatus::DRAFT)->count(),
            'retired' => ProductSpecification::query()->where('status', ProductSpecStatus::RETIRED)->count(),
            'requiredComponents' => count(GasComponent::all()),
        ];

        return ApiResponse::list(
            $rows,
            $paginator,
            $summary,
            ProductSpecification::query()->max('updated_at'),
            'Product specifications retrieved'
        );
    }

    public function show(string $id)
    {
        $spec = ProductSpecification::with('gasLimits')->find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }
        return $this->success(new ProductSpecificationDetailResource($spec), 'Product specification retrieved');
    }

    public function store(CreateProductSpecificationRequest $request)
    {
        $spec = $this->service->createDraft($request->validated());
        return $this->created(new ProductSpecificationDetailResource($spec->load('gasLimits')), 'Product specification created');
    }

    public function update(UpdateProductSpecificationRequest $request, string $id)
    {
        $spec = ProductSpecification::find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }
        $fresh = $this->service->updateMetadata($spec, $request->validated());
        return $this->success(new ProductSpecificationDetailResource($fresh->load('gasLimits')), 'Product specification updated');
    }

    public function activate(Request $request, string $id)
    {
        $spec = ProductSpecification::find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }

        $result = $this->service->activate($spec, $request->input('reason'));
        if (! ($result['ok'] ?? false)) {
            return $this->error(
                $result['code'] === 'PRODUCT_SPEC_INCOMPLETE'
                    ? 'Cannot activate: not all 6 components are configured.'
                    : 'Cannot activate from current status.',
                $result['code'] ?? 'INVALID_STATE_TRANSITION',
                409,
                $result['details'] ?? null
            );
        }
        return $this->success(new ProductSpecificationDetailResource($result['spec']->load('gasLimits')), 'Product specification activated');
    }

    public function retire(RetireProductSpecificationRequest $request, string $id)
    {
        $spec = ProductSpecification::find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }

        $result = $this->service->retire($spec, $request->validated()['reason']);
        if (! ($result['ok'] ?? false)) {
            return $this->error('Cannot retire from current status.', $result['code'] ?? 'INVALID_STATE_TRANSITION', 409);
        }
        return $this->success(new ProductSpecificationDetailResource($result['spec']->load('gasLimits')), 'Product specification retired');
    }

    public function addGasLimit(AddGasLimitRequest $request, string $id)
    {
        $spec = ProductSpecification::find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }
        if (! $spec->isEditable()) {
            return $this->error('Specification is not editable in its current status.', 'PRODUCT_SPEC_NOT_EDITABLE', 409);
        }

        $result = $this->service->addGasLimit($spec, $request->validated());
        if (! ($result['ok'] ?? false)) {
            return $this->error(
                'A gas-limit row already exists for this component on this specification.',
                $result['code'] ?? 'PRODUCT_SPEC_GAS_LIMIT_EXISTS',
                409
            );
        }
        return $this->created(new ProductGasLimitResource($result['row']), 'Gas limit row added');
    }

    public function updateGasLimit(UpdateGasLimitRequest $request, string $id, string $rowId)
    {
        $spec = ProductSpecification::find($id);
        if (! $spec) {
            return $this->error('Specification not found', 'PRODUCT_SPEC_NOT_FOUND', 404);
        }
        if (! $spec->isEditable()) {
            return $this->error('Specification is not editable in its current status.', 'PRODUCT_SPEC_NOT_EDITABLE', 409);
        }

        $row = ProductGasLimit::where('id', $rowId)->where('spec_id', $spec->id)->first();
        if (! $row) {
            return $this->error('Gas limit row not found on this specification.', 'PRODUCT_SPEC_GAS_LIMIT_NOT_FOUND', 404);
        }

        $fresh = $this->service->updateGasLimit($row, $request->validated());
        return $this->success(new ProductGasLimitResource($fresh), 'Gas limit row updated');
    }
}
