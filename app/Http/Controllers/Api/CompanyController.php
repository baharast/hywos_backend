<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Company::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('legal_name', 'like', $search);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $summary = [
            'total' => Company::query()->count(),
            'active' => Company::query()->where('is_active', true)->count(),
            'inactive' => Company::query()->where('is_active', false)->count(),
        ];

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $items = CompanyResource::collection($paginator->items());
        $lastUpdated = $query->max('updated_at');

        return ApiResponse::list($items, $paginator, $summary, $lastUpdated, 'Companies retrieved');
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();
        $company = Company::create($data);

        return $this->created(new CompanyResource($company), 'Company created');
    }

    public function show($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return $this->error('Company not found', 'COMPANY_NOT_FOUND', 404);
        }

        return $this->success(new CompanyResource($company), 'Company retrieved');
    }

    public function update(UpdateCompanyRequest $request, $id)
    {
        $company = Company::find($id);
        if (!$company) {
            return $this->error('Company not found', 'COMPANY_NOT_FOUND', 404);
        }

        $company->update($request->validated());

        return $this->success(new CompanyResource($company), 'Company updated');
    }

    public function activate($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return $this->error('Company not found', 'COMPANY_NOT_FOUND', 404);
        }

        $company->update(['is_active' => true]);

        return $this->success(new CompanyResource($company), 'Company activated');
    }

    public function deactivate($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return $this->error('Company not found', 'COMPANY_NOT_FOUND', 404);
        }

        $company->update(['is_active' => false]);

        return $this->success(new CompanyResource($company), 'Company deactivated');
    }

    public function destroy($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return $this->error('Company not found', 'COMPANY_NOT_FOUND', 404);
        }

        // Soft delete via deactivation
        $company->update(['is_active' => false]);

        return $this->success(new CompanyResource($company), 'Company deleted');
    }
}
