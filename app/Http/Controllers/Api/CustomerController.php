<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Customer::query();
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return \App\Services\ApiResponse::paginated($paginator, 'Customers retrieved');
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $customer = Customer::create($data);

        return $this->created(new CustomerResource($customer), 'Customer created');
    }

    public function show($id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        return $this->success(new CustomerResource($customer), 'Customer retrieved');
    }

    public function update(UpdateCustomerRequest $request, $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update($request->validated());

        return $this->success(new CustomerResource($customer), 'Customer updated');
    }

    public function activate(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => true]);

        return $this->success(new CustomerResource($customer), 'Customer activated');
    }

    public function deactivate(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => false]);

        return $this->success(new CustomerResource($customer), 'Customer deactivated');
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('delete', Customer::class);

        $customer = Customer::find($id);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }

        $customer->update(['is_active' => false]);

        return $this->success(null, 'Customer deactivated');
    }
}
